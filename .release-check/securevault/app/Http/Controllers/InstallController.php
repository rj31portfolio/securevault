<?php
namespace App\Http\Controllers;
use App\Models\{User,Role,Setting,StorageUsage};
use App\Services\{EnvironmentWriter,MailerSettings};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan,DB,Mail};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
class InstallController extends Controller {
    public function requirements(): array {
        $checks=['PHP 8.2 or later'=>version_compare(PHP_VERSION,'8.2.0','>='),'Writable environment directory'=>is_writable(base_path()),'Writable storage'=>is_writable(storage_path()),'Writable cache'=>is_writable(base_path('bootstrap/cache'))];
        foreach(['ctype','curl','dom','fileinfo','filter','hash','mbstring','openssl','pcre','pdo_mysql','session','tokenizer','xml','zip'] as $ext) $checks['PHP '.$ext]=extension_loaded($ext);
        return $checks;
    }
    public function index(Request $r) { return view('install',['step'=>(int)$r->session()->get('install_step',1),'checks'=>$this->requirements()]); }
    public function advance(Request $r) {
        $step=(int)$r->session()->get('install_step',1);
        if($step===1) {
            $r->validate(['token'=>'required|string|max:255']); $expected = (string)config('vault.installer_token');
            abort_unless(strlen($expected)>=32 && hash_equals($expected,$r->token),403,'Invalid installer token. Read INSTALL_ACCESS.txt in the private application directory.');
            $r->session()->put('installer_authorized',true);
        } else abort_unless($r->session()->get('installer_authorized'),403);
        if($step===2) { if(in_array(false,$this->requirements(),true)) return back()->withErrors(['server'=>'Resolve the failed server requirements first.']); }
        if($step===3) {
            $data=$r->validate(['db_host'=>['required','string','max:253','regex:/^[a-zA-Z0-9.\-]+$/'],'db_port'=>'required|integer|min:1|max:65535','db_database'=>['required','regex:/^[A-Za-z0-9_\-]+$/','max:64'],'db_username'=>'required|string|max:100','db_password'=>'nullable|string|max:1024']); $data['db_password']??='';
            try { $pdo=$this->connection($data); if(count($pdo->query('SHOW TABLES')->fetchAll())) return back()->withErrors(['database'=>'Use an empty database dedicated to SecureVault.']); }
            catch(\Throwable $e) { return back()->withErrors(['database'=>'Could not connect to MySQL. Check credentials and database privileges.']); }
            $r->session()->put('install_database',$data);
        }
        if($step===4) {
            $data=$r->validate(['site_name'=>'required|string|max:100','site_url'=>'required|url:http,https|max:255','timezone'=>['required',Rule::in(timezone_identifiers_list())],'admin_email'=>'required|email|max:255']);
            $parts=parse_url($data['site_url']); if(!empty($parts['user']) || !empty($parts['pass']) || !empty($parts['query']) || !empty($parts['fragment']) || (!empty($parts['path']) && $parts['path']!=='/')) return back()->withErrors(['site_url'=>'Use the site origin only, without a path, query, or credentials.']);
            if(!in_array($r->ip(),['127.0.0.1','::1'],true) && !app()->environment('testing') && !str_starts_with($data['site_url'],'https://')) return back()->withErrors(['site_url'=>'Production installation requires HTTPS.']);
            $r->session()->put('install_site',$data);
        }
        if($step===5) {
            $data=$r->validate(AdminController::smtpRules()); $data['password']??=''; $data['username']??='';
            if($r->input('action')==='test') {
                try { MailerSettings::apply($data); $transport=Mail::mailer()->getSymfonyTransport(); if(method_exists($transport,'start')) $transport->start(); Mail::raw('SecureVault installation SMTP test.',fn($m)=>$m->to($r->session()->get('install_site.admin_email'))->subject('SecureVault installation test')); }
                catch(\Throwable $e) { return back()->withErrors(['smtp'=>'SMTP test failed. Check TLS, host, port and credentials.']); }
                return back()->with('status','SMTP connected and test email submitted. Re-enter the password and continue.');
            }
            $r->session()->put('install_smtp',$data);
        }
        if($step===6) {
            $data=$r->validate(['name'=>'required|string|max:120','password'=>['required','confirmed','max:1024',Password::min(12)->mixedCase()->numbers()->symbols()]]);
            $r->session()->put('install_admin',['name'=>$data['name'],'password'=>\Illuminate\Support\Facades\Hash::make($data['password'])]);
        }
        if($step===7) return $this->finish($r);
        $r->session()->put('install_step',$step+1); return redirect('/install');
    }
    private function connection(array $d): \PDO { return new \PDO('mysql:host='.$d['db_host'].';port='.$d['db_port'].';dbname='.$d['db_database'].';charset=utf8mb4',$d['db_username'],$d['db_password'],[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_TIMEOUT=>10]); }
    private function finish(Request $r) {
        $handle=fopen(storage_path('app/installing.lock'),'c'); abort_unless($handle && flock($handle,LOCK_EX|LOCK_NB),409,'Installation is already running.');
        $oldEnv=is_file(base_path('.env')) ? file_get_contents(base_path('.env')) : '';
        try {
            abort_if(config('vault.installed') || is_file(storage_path('app/installed.lock')),404);
            $d=$r->session()->get('install_database'); $site=$r->session()->get('install_site'); $smtp=$r->session()->get('install_smtp'); $admin=$r->session()->get('install_admin'); abort_unless($d && $site && $smtp && $admin,422);
            if(count($this->connection($d)->query('SHOW TABLES')->fetchAll())) return back()->withErrors(['install'=>'Database is no longer empty. Use a fresh database before retrying.']);
            // Preserve the unique bootstrap key so the current encrypted installation session remains valid.
            abort_unless(strlen((string)config('app.key'))>20,500,'Package application key missing.');
            EnvironmentWriter::write(['APP_NAME'=>$site['site_name'],'APP_ENV'=>'production','APP_DEBUG'=>'false','APP_URL'=>rtrim($site['site_url'],'/'),'APP_TIMEZONE'=>$site['timezone'],'DB_CONNECTION'=>'mysql','DB_HOST'=>$d['db_host'],'DB_PORT'=>$d['db_port'],'DB_DATABASE'=>$d['db_database'],'DB_USERNAME'=>$d['db_username'],'DB_PASSWORD'=>$d['db_password'],'SESSION_DRIVER'=>'file','SESSION_ENCRYPT'=>'true','SESSION_SECURE_COOKIE'=>str_starts_with($site['site_url'],'https://') ? 'true':'false','CACHE_STORE'=>'file','QUEUE_CONNECTION'=>'sync']);
            config(['database.default'=>'mysql','database.connections.mysql.host'=>$d['db_host'],'database.connections.mysql.port'=>$d['db_port'],'database.connections.mysql.database'=>$d['db_database'],'database.connections.mysql.username'=>$d['db_username'],'database.connections.mysql.password'=>$d['db_password']]); DB::purge('mysql');
            if(Artisan::call('migrate',['--force'=>true])!==0) throw new \RuntimeException('Migration failed');
            DB::transaction(function() use($site,$smtp,$admin) {
                (new \Database\Seeders\VaultSeeder)->run(); Setting::put('site_name',$site['site_name']); Setting::put('smtp',$smtp,true);
                $user=new User(['name'=>$admin['name'],'email'=>strtolower($site['admin_email']),'password'=>$admin['password']]);
                $user->role_id=Role::where('name','super_admin')->sole()->id; $user->status='active'; $user->email_verified_at=now(); $user->terms_accepted_at=now(); $user->save(); StorageUsage::create(['user_id'=>$user->id]);
            });
            if(file_put_contents(storage_path('app/installed.lock'),now()->toIso8601String(),LOCK_EX)===false) throw new \RuntimeException('Cannot write installation lock');
            @chmod(storage_path('app/installed.lock'),0600); EnvironmentWriter::write(['VAULT_INSTALLED'=>'true','INSTALLER_TOKEN'=>'']);
            $r->session()->forget(['install_database','install_smtp','install_admin','install_site','install_step','installer_authorized']);
            $r->session()->regenerate(); return response()->view('install-complete');
        } catch(\Throwable $e) {
            // Never send exception messages containing credentials to the browser or log.
            if(!is_file(storage_path('app/installed.lock'))) file_put_contents(base_path('.env'),$oldEnv,LOCK_EX);
            return back()->withErrors(['install'=>'Installation did not finish. Credentials were not displayed. Check database privileges and writable paths; after a partial migration restore an empty database before retrying.']);
        } finally { flock($handle,LOCK_UN); fclose($handle); }
    }
}
