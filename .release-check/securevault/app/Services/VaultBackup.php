<?php
namespace App\Services;
use App\Models\{VaultFile,Setting};
use Illuminate\Support\Facades\DB;
class VaultBackup {
    public function create(): string {
        $key=base64_decode((string)config('vault.backup_key'),true);
        if($key===false || strlen($key)!==32) throw new \RuntimeException('Set BACKUP_KEY to a separate base64-encoded 32-byte key. Store it offline.');
        $directory=storage_path('app/private/backups'); if(!is_dir($directory)) mkdir($directory,0700,true);
        $id=now()->format('Ymd-His').'-'.bin2hex(random_bytes(5)); $dump=$directory.'/'.$id.'.ndjson'; $plain=$directory.'/'.$id.'.zip'; $encrypted=$directory.'/'.$id.'.svb';
        $lock=fopen($directory.'/backup.lock','c'); if(!flock($lock,LOCK_EX|LOCK_NB)) throw new \RuntimeException('A backup is already running');
        try {
            $handle=fopen($dump,'wb');
            try {
                DB::transaction(function() use($handle) {
                    foreach(['roles','permissions','permission_role','users','categories','files','storage_usage','settings','user_activities','file_views','login_histories','security_logs','user_sessions','email_verification_logs','migrations'] as $table) {
                        DB::table($table)->orderBy(array_key_exists('id',(array)(DB::table($table)->first() ?? [])) ? 'id' : ($table==='settings'?'key':'role_id'))->chunk(500,function($rows) use($handle,$table) { foreach($rows as $row) if(fwrite($handle,json_encode(['table'=>$table,'row'=>(array)$row],JSON_THROW_ON_ERROR)."\n")===false) throw new \RuntimeException('Backup dump write failed'); });
                    }
                });
            } finally { fclose($handle); }
            $zip=new \ZipArchive; if($zip->open($plain,\ZipArchive::CREATE|\ZipArchive::OVERWRITE)!==true) throw new \RuntimeException('Cannot create backup archive');
            $zip->addFile($dump,'database.ndjson');
            foreach(VaultFile::withTrashed()->where('scan_status','!=','purged')->cursor() as $file) { $path=\Illuminate\Support\Facades\Storage::disk('local')->path($file->disk_path); if(!is_file($path)) throw new \RuntimeException('A recorded media file is missing'); $zip->addFile($path,'private/'.$file->disk_path); }
            if(!$zip->close()) throw new \RuntimeException('Cannot finalize backup');
            $input=fopen($plain,'rb'); $output=fopen($encrypted.'.tmp','wb'); fwrite($output,'SVB1'); $index=0;
            try { while(!feof($input)) { $chunk=fread($input,1048576); if($chunk==='') break; $iv=random_bytes(12); $tag=''; $cipher=openssl_encrypt($chunk,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'SVB1:'.$index++); if($cipher===false) throw new \RuntimeException('Backup encryption failed'); fwrite($output,pack('N',strlen($cipher)).$iv.$tag.$cipher); } $iv=random_bytes(12); $tag=''; openssl_encrypt('','aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'SVB1:final:'.$index); fwrite($output,pack('N',0).$iv.$tag); } finally { fclose($input); fclose($output); }
            if(!rename($encrypted.'.tmp',$encrypted)) throw new \RuntimeException('Backup finalization failed'); @chmod($encrypted,0600);
            Audit::record('backup_created','Encrypted backup created: '.$id);
            $cutoff=time()-86400*(int)Setting::read('backup_retention_days',14); foreach(glob($directory.'/*.svb') as $old) if(filemtime($old)<$cutoff) unlink($old);
            return $encrypted;
        } finally { foreach([$dump,$plain,$encrypted.'.tmp'] as $temp) if(is_file($temp)) unlink($temp); flock($lock,LOCK_UN); fclose($lock); }
    }
}
