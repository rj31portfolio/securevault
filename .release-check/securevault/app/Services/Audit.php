<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class Audit {
    public static function record(string $type,string $description,?User $user = null,string $table = 'user_activities'): void {
        if (!in_array($table,['user_activities','security_logs','login_histories','email_verification_logs'],true)) throw new \InvalidArgumentException('Invalid audit table');
        $r = request(); $user ??= $r->user(); $agent = substr((string)$r->userAgent(),0,500);
        DB::table($table)->insert(['user_id'=>$user?->id,'email'=>$user?->email,'type'=>$type,'description'=>$description,'ip_address'=>$r->ip(),'browser'=>$agent,
            'device'=>preg_match('/Mobile|Android|iPhone/i',$agent) ? 'mobile' : 'desktop/other','created_at'=>now(),'updated_at'=>now()]);
    }
}
