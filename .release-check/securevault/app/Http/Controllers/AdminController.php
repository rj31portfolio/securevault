<?php
namespace App\Http\Controllers;
use App\Models\{User,Category,Setting,VaultFile};
use App\Services\{Audit,MailerSettings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Gate,Mail,Storage};
use Illuminate\Validation\Rule;
class AdminController extends Controller {
    public function users(Request $r) {
        $q = User::with('role');
        if ($r->filled('q')) $q->where(fn($q)=>$q->where('name','like','%'.mb_substr($r->string('q'),0,120).'%')->orWhere('email','like','%'.mb_substr($r->string('q'),0,120).'%'));
        if ($r->filled('status')) $q->where('status',$r->query('status'));
        return view('admin.users',['users'=>$q->latest()->paginate(25)->withQueryString()]);
    }
    public function user(User $user) { return view('admin.user',['user'=>$user,'files'=>$user->files()->with('category')->latest()->paginate(12),'logins'=>DB::table('login_histories')->where('user_id',$user->id)->latest('id')->limit(20)->get(),'activities'=>DB::table('user_activities')->where('user_id',$user->id)->latest('id')->limit(20)->get()]); }
    public function userStatus(Request $r,User $user) {
        abort_unless($r->user()->hasPermission('users.manage'),403);
        $data = $r->validate(['status'=>['required',Rule::in(['active','pending','suspended','blocked'])]]);
        abort_if($user->isAdmin(),403,'Administrator status cannot be changed here.');
        DB::transaction(function () use ($user,$data) { $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail(); $locked->forceFill(['status'=>$data['status'],'session_version'=>$locked->session_version+1])->save(); DB::table('user_sessions')->where('user_id',$user->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]); Audit::record('account_status','Changed user '.$user->id.' to '.$data['status']); });
        MailerSettings::notify($user,'SecureVault account status','Your account status is now '.$data['status'].'. Contact your administrator with any questions.');
        return back()->with('status','Account status updated and existing sessions invalidated.');
    }
    public function deleteUser(Request $r,User $user) {
        abort_unless($r->user()->hasPermission('users.manage'),403); abort_if($user->isAdmin(),403);
        $r->validate(['confirm_email'=>['required',Rule::in([$user->email])]]);
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->files()->exists(),422,'Delete or retain-remove this account’s active files before deleting the account.');
            $locked->forceFill(['status'=>'blocked','session_version'=>$locked->session_version+1])->save(); $locked->delete();
            DB::table('user_sessions')->where('user_id',$user->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            Audit::record('account_deleted','Soft-deleted user '.$user->id.'; identity and audit records retained.');
        });
        return redirect()->route('admin.users')->with('status','Account disabled and soft-deleted. Audit records and retained files are preserved.');
    }
    public function categories() { return view('admin.categories',['categories'=>Category::withCount('files')->orderBy('name')->get()]); }
    private function categoryData(Request $r,?Category $category = null): array {
        $data = $r->validate(['name'=>['required','string','max:100',Rule::unique('categories')->ignore($category?->id)],'allowed_extensions'=>'required|array|min:1','allowed_extensions.*'=>[Rule::in(array_keys(config('vault.mime_map')))],'max_upload_mb'=>'required|integer|min:1|max:2048','enabled'=>'nullable|boolean','is_screenshot'=>'nullable|boolean']);
        $data['enabled'] = $r->boolean('enabled'); $data['is_screenshot'] = $r->boolean('is_screenshot'); return $data;
    }
    public function saveCategory(Request $r,?Category $category = null) { $data = $this->categoryData($r,$category); if ($category) $category->update($data); else $category = Category::create($data); Audit::record('category_saved','Saved category '.$category->id); return back()->with('status','Category saved.'); }
    public function deleteCategory(Category $category) { abort_if($category->files()->withTrashed()->exists(),422,'Categories referenced by active or retained files cannot be deleted. Disable this category instead.'); $id=$category->id; $category->delete(); Audit::record('category_deleted','Deleted unused category '.$id); return back()->with('status','Unused category deleted.'); }
    public function settings() {
        $keys = ['site_name','registration_enabled','email_verification_required','admin_approval_required','max_upload_mb','allowed_extensions','storage_limit_mb','user_quota_mb','session_timeout','watermark_enabled','watermark_text','file_retention_days','deleted_file_retention_days','log_retention_days','backup_enabled','backup_retention_days'];
        $settings=[]; foreach($keys as $key) $settings[$key]=Setting::read($key);
        $smtp = Setting::read('smtp',[]); unset($smtp['password']); return view('admin.settings',compact('settings','smtp'));
    }
    public function saveSettings(Request $r) {
        abort_unless($r->user()->hasPermission('settings.manage'),403);
        $data = $r->validate(['site_name'=>'required|string|max:100','max_upload_mb'=>'required|integer|min:1|max:2048','storage_limit_mb'=>'required|integer|min:1|max:10485760','user_quota_mb'=>'required|integer|min:1|max:10485760','session_timeout'=>'required|integer|min:5|max:1440','watermark_text'=>'required|string|max:100','file_retention_days'=>'required|integer|min:0|max:36500','deleted_file_retention_days'=>'required|integer|min:1|max:36500','log_retention_days'=>'required|integer|min:30|max:36500','backup_retention_days'=>'required|integer|min:1|max:365','allowed_extensions'=>'required|array|min:1','allowed_extensions.*'=>[Rule::in(array_keys(config('vault.mime_map')))],'logo'=>'nullable|file|mimes:png,jpg,webp|max:512','favicon'=>'nullable|file|mimes:png|max:128']);
        unset($data['logo'],$data['favicon']);
        foreach(['registration_enabled','email_verification_required','admin_approval_required','watermark_enabled','backup_enabled'] as $key) $data[$key]=$r->boolean($key);
        DB::transaction(function() use($data) { foreach($data as $key=>$value) Setting::put($key,$value); Audit::record('settings_saved','Application settings updated.'); });
        foreach(['logo','favicon'] as $key) if($r->hasFile($key)) { $path=$r->file($key)->store('branding','local'); $old=Setting::read($key); Setting::put($key,$path); if($old) Storage::disk('local')->delete($old); }
        return back()->with('status','Settings saved.');
    }
    public static function smtpRules(bool $passwordRequired = false): array {
        return ['host'=>['required','string','max:253','regex:/^[a-zA-Z0-9.\-]+$/'],'port'=>'required|integer|min:1|max:65535','username'=>'nullable|string|max:255','password'=>($passwordRequired ? 'required' : 'nullable').'|string|max:1024','encryption'=>['required',Rule::in(['tls','ssl'])],'from_address'=>'required|email|max:255','from_name'=>'required|string|max:100'];
    }
    public function smtp(Request $r) {
        abort_unless($r->user()->hasPermission('settings.manage'),403); $data = $r->validate(self::smtpRules());
        $data['password'] = ($data['password'] ?? '') ?: (Setting::read('smtp',[])['password'] ?? ''); $data['username'] ??= '';
        Setting::put('smtp',$data,true); MailerSettings::apply($data); Audit::record('smtp_saved','SMTP settings updated. Credentials are encrypted.'); return back()->with('status','SMTP settings saved.');
    }
    public function testSmtp(Request $r) {
        $r->validate(['recipient'=>'required|email|max:255']);
        try { $transport=Mail::mailer()->getSymfonyTransport(); if(method_exists($transport,'start')) $transport->start(); Mail::raw('SecureVault SMTP connection and delivery test.',fn($m)=>$m->to($r->recipient)->subject('SecureVault SMTP test')); Audit::record('smtp_test','SMTP test message submitted.'); return back()->with('status','Connection succeeded and the test message was submitted. Check the recipient inbox.'); }
        catch(\Throwable $e) { Audit::record('smtp_test_failed','SMTP test failed.',null,'security_logs'); return back()->withErrors(['smtp'=>'SMTP connection or submission failed. Check the host, port, TLS mode, and credentials.']); }
    }
    public function branding(string $kind) { abort_unless(in_array($kind,['logo','favicon'],true),404); $path=Setting::read($kind); abort_unless($path && Storage::disk('local')->exists($path),404); return response()->file(Storage::disk('local')->path($path)); }
}
