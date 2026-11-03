<?php
namespace App\Http\Middleware;
use App\Models\Setting;
use App\Services\MailerSettings;
use Closure;
use Illuminate\Http\Request;
class VaultContext {
    public function handle(Request $request,Closure $next) {
        $installed = config('vault.installed') || is_file(storage_path('app/installed.lock'));
        if (!$installed && !$request->is('install','install/*')) return redirect('/install');
        if ($installed && $request->is('install','install/*')) abort(404);
        if ($installed) { config(['app.name'=>Setting::read('site_name','SecureVault'),'session.lifetime'=>Setting::read('session_timeout',60)]); MailerSettings::apply(); }
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options','nosniff'); $response->headers->set('X-Frame-Options','SAMEORIGIN');
        $response->headers->set('Referrer-Policy','same-origin'); $response->headers->set('Permissions-Policy','camera=(), microphone=(), geolocation=()');
        if (!$response->headers->has('Content-Security-Policy')) $response->headers->set('Content-Security-Policy',"default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; media-src 'self'; frame-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        $response->headers->set('Cache-Control','private, no-store, max-age=0');
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security','max-age=31536000; includeSubDomains');
        return $response;
    }
}
