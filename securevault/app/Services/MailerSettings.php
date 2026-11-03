<?php
namespace App\Services;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
class MailerSettings {
    public static function apply(?array $smtp = null): void {
        $smtp ??= Setting::read('smtp'); if (!$smtp) return;
        config(['mail.default'=>'smtp','mail.mailers.smtp'=>['transport'=>'smtp','scheme'=>$smtp['encryption']==='ssl' ? 'smtps' : 'smtp','host'=>$smtp['host'],'port'=>(int)$smtp['port'],
            'username'=>$smtp['username'] ?: null,'password'=>$smtp['password'] ?: null,'timeout'=>15,'local_domain'=>parse_url(config('app.url'),PHP_URL_HOST),'require_tls'=>$smtp['encryption']==='tls'],
            'mail.from.address'=>$smtp['from_address'],'mail.from.name'=>$smtp['from_name']]);
        Mail::purge('smtp');
    }
    public static function notify(\App\Models\User $user,string $subject,string $message): void {
        try { $user->notify(new \App\Notifications\VaultNotification($subject,$message)); }
        catch (\Throwable $e) { Audit::record('email_failed','Notification delivery failed. Check SMTP configuration.',$user,'security_logs'); }
    }
}
