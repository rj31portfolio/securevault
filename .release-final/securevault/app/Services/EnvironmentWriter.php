<?php
namespace App\Services;
class EnvironmentWriter {
    public static function write(array $values): void {
        $path = base_path('.env'); $text = is_file($path) ? file_get_contents($path) : '';
        foreach($values as $key=>$value) {
            if(!preg_match('/^[A-Z_]+$/',$key)) throw new \InvalidArgumentException('Invalid environment key');
            $value = (string)$value;
            if(preg_match('/[\r\n\x00]/',$value)) throw new \InvalidArgumentException('Newlines are prohibited in environment values');
            // Single-quoted dotenv values avoid expansion of $, #, and double quotes.
            $encoded = "'".str_replace(['\\',"'"],['\\\\',"\\'"],$value)."'";
            $line = $key.'='.$encoded;
            if(preg_match('/^'.preg_quote($key,'/').'=/m',$text)) $text=preg_replace_callback('/^'.preg_quote($key,'/').'=.*$/m',fn()=>$line,$text);
            else $text.="\n".$line;
        }
        $temp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
        if(file_put_contents($temp,rtrim($text)."\n",LOCK_EX)===false) throw new \RuntimeException('Environment is not writable');
        @chmod($temp,0600);
        if(!rename($temp,$path)) { @unlink($temp); throw new \RuntimeException('Environment replacement failed'); }
    }
}
