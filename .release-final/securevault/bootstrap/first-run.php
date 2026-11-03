<?php
// Runs before Laravel's encrypted session middleware. Never generates shared release secrets.
$root=dirname(__DIR__);
if (!is_file($root.'/storage/app/installed.lock')) {
    $guard=@fopen($root.'/storage/app/bootstrap.lock','c');
    if (!$guard || !flock($guard,LOCK_EX)) { http_response_code(503); exit('SecureVault requires writable private storage.'); }
    try {
        $path=$root.'/.env';
        $text=is_file($path) ? file_get_contents($path) : file_get_contents($root.'/.env.install');
        $changed=!is_file($path);
        if (!preg_match('/^APP_KEY=.+$/m',$text)) { $text=preg_replace('/^APP_KEY=.*$/m','APP_KEY=base64:'.base64_encode(random_bytes(32)),$text); $changed=true; }
        if (!preg_match('/^INSTALLER_TOKEN=.{32,}$/m',$text)) {
            $token=bin2hex(random_bytes(32)); $text=preg_replace('/^INSTALLER_TOKEN=.*\R?/m','',$text); $text.="\nINSTALLER_TOKEN=".$token."\n";
            if(file_put_contents($root.'/INSTALL_ACCESS.txt',"SecureVault installation access token\n\n".$token."\n\nEnter this token at /install. Delete this file after installation.\n",LOCK_EX)===false) throw new RuntimeException('Token write failed');
            @chmod($root.'/INSTALL_ACCESS.txt',0600); $changed=true;
        }
        if($changed) { $temporary=$path.'.bootstrap.tmp'; if(file_put_contents($temporary,$text,LOCK_EX)===false) throw new RuntimeException('Environment write failed'); @chmod($temporary,0600); if(!rename($temporary,$path)) throw new RuntimeException('Environment rename failed'); }
    } catch (Throwable $e) { http_response_code(503); exit('SecureVault setup could not initialize. Check private directory permissions.'); }
    finally { flock($guard,LOCK_UN); fclose($guard); }
}
