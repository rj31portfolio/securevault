<?php
// Run on the development machine after tests pass. Never bundle local secrets or user data.
$root=dirname(__DIR__); $target=$root.'/dist';
if(!extension_loaded('zip')) throw new RuntimeException('PHP zip extension is required');
foreach(['vendor/autoload.php','public/assets/bootstrap.min.css','public/assets/vault.css','public/assets/vault.js'] as $file) if(!is_file($root.'/'.$file)) throw new RuntimeException('Missing release prerequisite: '.$file);
if(!is_dir($target)) mkdir($target,0755,true);
$zip=new ZipArchive; $name=$target.'/SecureVault.zip'; if($zip->open($name,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot create ZIP');
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)); $count=0;
foreach($iterator as $file) {
    if(!$file->isFile()) continue; $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    if(preg_match('~^(?:dist|node_modules|\.git|\.phpunit.cache|tests)(?:/|$)~',$relative)) continue;
    if(preg_match('~^(?:\.env(?:\..*)?|INSTALL_ACCESS\.txt|phpunit.*|BUILD_STATUS\.md)$~',$relative) && $relative!=='.env.install' && $relative!=='.env.example') continue;
    if(str_starts_with($relative,'storage/') && basename($relative)!=='.gitignore') continue;
    if(str_starts_with($relative,'bootstrap/cache/') && basename($relative)!=='.gitignore') continue;
    if(preg_match('~^database/.*\.(?:sqlite|db)$~',$relative)) continue;
    $zip->addFile($file->getPathname(),'securevault/'.$relative); $count++;
}
foreach(['storage/app/private','storage/app/public','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','bootstrap/cache'] as $dir) $zip->addEmptyDir('securevault/'.$dir);
$zip->close(); file_put_contents($name.'.sha256',hash_file('sha256',$name).'  SecureVault.zip'.PHP_EOL);
echo "Created $name ($count files)\n";
