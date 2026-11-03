<?php
namespace App\Http\Controllers;
use App\Models\{User,Role,Setting,StorageUsage};
use App\Services\{Audit,MailerSettings};
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\{Auth,DB,Hash,Password};
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class AuthController extends Controller {
    public function loginForm() { return view('auth.form',['mode'=>'login']); }
    public function registerForm() { abort_unless(Setting::read('registration_enabled',true),403,'Registration is closed.'); return view('auth.form',['mode'=>'register']); }
    public function login(Request $r) {
        $data = $r->validate(['email'=>'required|email|max:255','password'=>'required|string|max:1024']);
        $user = User::where('email',Str::lower($data['email']))->first();
        if (!$user || !Hash::check($data['password'],$user->password) || in_array($user->status,['blocked','suspended'],true)) {
            Audit::record('failed_login','Unsuccessful sign-in.',$user,'login_histories');
            Audit::record('failed_login','Unsuccessful sign-in.',$user,'security_logs');
            if ($user) {
                $attempts = DB::table('login_histories')->where('user_id',$user->id)->where('type','failed_login')->where('created_at','>=',now()->subMinutes(15))->count();
                if ($attempts === 5) MailerSettings::notify($user,'SecureVault security alert','We detected repeated unsuccessful sign-in attempts. Review your account security.');
            }
            throw ValidationException::withMessages(['email'=>'The provided credentials could not be accepted.']);
        }
        Auth::login($user); $r->session()->regenerate();
        $r->session()->put(['session_version'=>$user->session_version,'vault_last_activity'=>time()]);
        DB::table('user_sessions')->insert(['user_id'=>$user->id,'session_hash'=>hash('sha256',$r->session()->getId()),'ip_address'=>$r->ip(),'browser'=>substr((string)$r->userAgent(),0,500),'last_seen_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $user->forceFill(['last_login_at'=>now()])->save();
        Audit::record('login','Signed in.',$user); Audit::record('login','Signed in.',$user,'login_histories');
        return redirect()->intended('/dashboard');
    }
    public function register(Request $r) {
        abort_unless(Setting::read('registration_enabled',true),403);
        $data = $r->validate(['name'=>'required|string|max:120','email'=>'required|email|max:255','password'=>['required','confirmed','max:1024',PasswordRule::min(12)->mixedCase()->numbers()->symbols()],'terms'=>'accepted']);
        $email = Str::lower($data['email']);
        // The response is deliberately the same for existing and new addresses.
        if (!User::withTrashed()->where('email',$email)->exists()) {
            $user = DB::transaction(function () use ($data,$email) {
                $user = new User(['name'=>$data['name'],'email'=>$email,'password'=>$data['password']]);
                $user->role_id = Role::where('name','user')->sole()->id; $user->terms_accepted_at = now();
                $user->status = Setting::read('admin_approval_required',false) ? 'pending' : 'active';
                if (!Setting::read('email_verification_required',true)) $user->email_verified_at = now();
                $user->save(); StorageUsage::create(['user_id'=>$user->id]); Audit::record('registration','Account registered.',$user); return $user;
            });
            if (!$user->hasVerifiedEmail()) $this->sendVerification($user);
            else MailerSettings::notify($user,'Welcome to SecureVault','Your account has been created. Sign in to check your account status.');
        }
        return redirect('/login')->with('status','If this address is eligible, your account was created. Check your email for verification, then sign in.');
    }
    private function sendVerification(User $user): void {
        try { $user->sendEmailVerificationNotification(); Audit::record('verification_sent','Verification link sent.',$user,'email_verification_logs'); }
        catch (\Throwable $e) { Audit::record('verification_failed','Verification email could not be sent.',$user,'security_logs'); }
    }
    public function verificationNotice() { return view('auth.verify'); }
    public function resend(Request $r) { if (!$r->user()->hasVerifiedEmail()) $this->sendVerification($r->user()); return back()->with('status','A verification link has been requested. Check your inbox.'); }
    public function verify(EmailVerificationRequest $r) {
        if (!$r->user()->hasVerifiedEmail()) {
            $r->fulfill(); Audit::record('email_verified','Email address verified.'); Audit::record('email_verified','Email address verified.',null,'email_verification_logs');
            MailerSettings::notify($r->user(),'Welcome to SecureVault','Your email address is verified. Your dashboard is available once your account is approved.');
        }
        return redirect('/dashboard')->with('status','Your email is verified.');
    }
    public function forgotForm() { return view('auth.form',['mode'=>'forgot']); }
    public function forgot(Request $r) {
        $r->validate(['email'=>'required|email|max:255']);
        try { Password::sendResetLink(['email'=>Str::lower($r->email)]); } catch (\Throwable $e) { Audit::record('reset_email_failed','Password reset mail delivery failed.',null,'security_logs'); }
        return back()->with('status','If an eligible account exists, a password reset link has been sent.');
    }
    public function resetForm(Request $r,string $token) { return view('auth.form',['mode'=>'reset','token'=>$token,'email'=>$r->query('email')]); }
    public function reset(Request $r) {
        $data = $r->validate(['token'=>'required|string','email'=>'required|email','password'=>['required','confirmed','max:1024',PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $data['email'] = Str::lower($data['email']);
        $status = Password::reset($data,function (User $user,string $password) {
            $user->forceFill(['password'=>$password,'remember_token'=>Str::random(60),'session_version'=>$user->session_version+1])->save();
            DB::table('user_sessions')->where('user_id',$user->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            Audit::record('password_reset','Password reset; all previous sessions invalidated.',$user);
            MailerSettings::notify($user,'SecureVault password changed','Your password was reset. All previous sessions have been invalidated.');
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) throw ValidationException::withMessages(['email'=>'This reset link is invalid or expired. Request a new link.']);
        return redirect('/login')->with('status','Password changed. Sign in with your new password.');
    }
    public function logout(Request $r) {
        Audit::record('logout','Signed out.'); DB::table('user_sessions')->where('session_hash',hash('sha256',$r->session()->getId()))->update(['revoked_at'=>now()]);
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login');
    }
    public function profile() { return view('profile'); }
    public function updateProfile(Request $r) {
        $data = $r->validate(['name'=>'required|string|max:120']); $r->user()->update($data); Audit::record('profile_updated','Display name updated.'); return back()->with('status','Profile updated.');
    }
    public function changePassword(Request $r) {
        $data = $r->validate(['current_password'=>'required|current_password','password'=>['required','confirmed','different:current_password','max:1024',PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $user = $r->user(); $user->forceFill(['password'=>$data['password'],'session_version'=>$user->session_version+1,'remember_token'=>Str::random(60)])->save();
        Audit::record('password_changed','Password changed; all sessions invalidated.');
        DB::table('user_sessions')->where('user_id',$user->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        MailerSettings::notify($user,'SecureVault password changed','Your password changed. Sign in again on your devices.');
        Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login')->with('status','Password changed. Sign in again.');
    }
}
