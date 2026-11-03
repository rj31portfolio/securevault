<?php
// Security operator utility. The key is supplied through BACKUP_KEY, never a CLI argument.
if($argc!==3) { fwrite(STDERR,"Usage: php tools/decrypt-backup.php source.svb destination.zip\n"); exit(1); }
$key=base64_decode((string)getenv('BACKUP_KEY'),true); if($key===false || strlen($key)!==32) throw new RuntimeException('BACKUP_KEY must be base64 of 32 bytes');
if(is_file($argv[2])) throw new RuntimeException('Destination already exists');
$input=fopen($argv[1],'rb'); $output=fopen($argv[2],'xb'); $ok=false; $error=null;
function readExact($stream,int $n): string { $data=''; while(strlen($data)<$n && !feof($stream)) $data.=fread($stream,$n-strlen($data)); if(strlen($data)!==$n) throw new RuntimeException('Truncated backup'); return $data; }
try {
    if(readExact($input,4)!=='SVB1') throw new RuntimeException('Invalid backup format'); $index=0;
    while(true) { $size=unpack('N',readExact($input,4))[1]; if($size===0) { $iv=readExact($input,12); $tag=readExact($input,16); if(openssl_decrypt('','aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'SVB1:final:'.$index)===false) throw new RuntimeException('Backup final authentication failed'); if(fread($input,1)!=='') throw new RuntimeException('Trailing backup content'); break; } if($size>1048576) throw new RuntimeException('Invalid frame length'); $iv=readExact($input,12); $tag=readExact($input,16); $data=openssl_decrypt(readExact($input,$size),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'SVB1:'.$index++); if($data===false) throw new RuntimeException('Backup authentication failed'); fwrite($output,$data); }
    $ok=true;
} catch(Throwable $e) { $error=$e; } finally { fclose($input); fclose($output); if(!$ok) unlink($argv[2]); }
if($error) { fwrite(STDERR,"Backup authentication or format validation failed.\n"); exit(1); }
echo "Decrypted to ".$argv[2].". Protect this archive as confidential data.\n";
