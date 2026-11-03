<?php
$root=getenv('SMOKE_ROOT') ?: 'D:/Working/data/.release-check/securevault'; $base=getenv('SMOKE_URL') ?: 'http://127.0.0.1:8098'; $cookies=getenv('SMOKE_COOKIES') ?: 'D:/Working/data/.tmp/smoke-cookies.txt';
function requestPage(string $path,array $data=[]): array {
    global $base,$cookies; $c=curl_init($base.$path); curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_COOKIEJAR=>$cookies,CURLOPT_COOKIEFILE=>$cookies,CURLOPT_TIMEOUT=>45]);
    if($data) curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data)]);
    $body=curl_exec($c); if($body===false) throw new RuntimeException(curl_error($c)); $code=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c); return [$code,$body];
}
function csrf(string $html): string { if(!preg_match('/name="_token" value="([^"]+)"/',$html,$m)) throw new RuntimeException('CSRF token missing'); return html_entity_decode($m[1]); }
if(is_file($root.'/storage/app/installed.lock')) goto login;
[$code,$html]=requestPage('/install'); if($code!==200) throw new RuntimeException('Installer unavailable '.$code);
$tokenText=file_get_contents($root.'/INSTALL_ACCESS.txt'); preg_match('/\b[a-f0-9]{64}\b/',$tokenText,$m);
$stages=[['token'=>$m[0]],[],['db_host'=>'127.0.0.1','db_port'=>3307,'db_database'=>getenv('SMOKE_DB') ?: 'securevault_install_test','db_username'=>'root','db_password'=>'VaultTestLocalOnly42'],['site_name'=>'Install Smoke Vault','site_url'=>$base,'timezone'=>'Asia/Kolkata','admin_email'=>'smoke-admin@example.test'],['host'=>'smtp.example.test','port'=>587,'username'=>'test','password'=>'Smoke!Smtp1234','encryption'=>'tls','from_address'=>'vault@example.test','from_name'=>'Smoke Vault'],['name'=>'Smoke Admin','password'=>'Smoke!Admin1234','password_confirmation'=>'Smoke!Admin1234'],[]];
foreach($stages as $i=>$data) {
    $data['_token']=csrf($html); [$code,$body]=requestPage('/install',$data);
    if($i<6) { if($code!==302) throw new RuntimeException('Step '.($i+1).' status '.$code.' '.strip_tags($body)); [$code,$html]=requestPage('/install'); if(!str_contains($html,'STEP '.($i+2).' OF 8')) throw new RuntimeException('Step '.($i+1).' failed: '.strip_tags($html)); }
    else { if($code!==200 || !str_contains($body,'Your vault is ready.')) throw new RuntimeException('Installation failed: '.strip_tags($body)); }
    echo 'Installer step '.($i+1)." passed\n";
}
[$code]=requestPage('/install'); if($code!==404) throw new RuntimeException('Installer not locked');
login:
[$code,$html]=requestPage('/login'); if($code!==200) throw new RuntimeException('Login form unavailable');
[$code,$loginBody]=requestPage('/login',['_token'=>csrf($html),'email'=>'smoke-admin@example.test','password'=>'Smoke!Admin1234']); if($code!==302) throw new RuntimeException('Admin login failed '.$code.' '.substr(strip_tags($loginBody),0,1000));
[$code,$html]=requestPage('/dashboard'); if($code!==200 || !str_contains($html,'Vault overview')) throw new RuntimeException('Admin dashboard failed');
echo "Fresh ZIP HTTP installer, post-install lock, admin login and dashboard passed.\n";
