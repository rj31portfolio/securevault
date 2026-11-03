<?php
$path='D:/Working/data/securevault/dist/SecureVault.zip'; $zip=new ZipArchive;
if($zip->open($path)!==true) throw new RuntimeException('Cannot open release ZIP');
foreach(['securevault/vendor/autoload.php','securevault/public/assets/bootstrap.min.css','securevault/.env.install','securevault/bootstrap/first-run.php','securevault/BUILD_STATUS.md','securevault/INSTALLATION.md','securevault/public/.htaccess'] as $entry) if($zip->locateName($entry)===false) throw new RuntimeException('Missing '.$entry);
foreach(['securevault/.env','securevault/INSTALL_ACCESS.txt','securevault/storage/app/installed.lock','securevault/bootstrap/cache/config.php','securevault/vendor/phpunit/phpunit/phpunit'] as $entry) if($zip->locateName($entry)!==false) throw new RuntimeException('Unexpected release entry '.$entry);
for($i=0;$i<$zip->numFiles;$i++) { $name=$zip->getNameIndex($i); if(str_contains($name,'..')) throw new RuntimeException('Unexpected archive path'); if(str_starts_with($name,'securevault/storage/') && !str_ends_with($name,'/') && !str_ends_with($name,'/.gitignore')) throw new RuntimeException('Runtime data in archive'); }
if(!$zip->extractTo('D:/Working/data/.release-final')) throw new RuntimeException('Extraction failed');
echo 'Verified '.$zip->numFiles.' ZIP entries and extracted final production-dependency package.'.PHP_EOL; $zip->close();
$template='D:/Working/data/.release-final/securevault/.env.install'; file_put_contents($template,str_replace('SESSION_SECURE_COOKIE=true','SESSION_SECURE_COOKIE=false',file_get_contents($template)));
