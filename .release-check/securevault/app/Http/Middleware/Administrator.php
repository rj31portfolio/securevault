<?php
namespace App\Http\Middleware;
use App\Services\Audit;
use Closure;
use Illuminate\Http\Request;
class Administrator {
    public function handle(Request $request,Closure $next) {
        if (!$request->user()->isAdmin()) { Audit::record('unauthorized_admin','Denied access to administrator route.',null,'security_logs'); abort(403); }
        return $next($request);
    }
}
