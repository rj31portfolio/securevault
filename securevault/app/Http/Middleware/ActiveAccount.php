<?php
namespace App\Http\Middleware;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB};
class ActiveAccount {
    public function handle(Request $request,Closure $next) {
        $user = $request->user();
        $last = (int)$request->session()->get('vault_last_activity',time());
        if ((int)$request->session()->get('session_version') !== (int)$user->session_version || time()-$last > 60*(int)Setting::read('session_timeout',60)) {
            Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect('/login')->withErrors(['email'=>'Your session expired. Please sign in again.']);
        }
        if ($user->status !== 'active') return response()->view('auth.pending',['status'=>$user->status],403);
        if (Setting::read('email_verification_required',true) && !$user->hasVerifiedEmail()) return redirect()->route('verification.notice');
        $request->session()->put('vault_last_activity',time());
        DB::table('user_sessions')->where('session_hash',hash('sha256',$request->session()->getId()))->update(['last_seen_at'=>now(),'updated_at'=>now()]);
        return $next($request);
    }
}
