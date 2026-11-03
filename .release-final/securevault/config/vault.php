<?php
return ['installed'=>env('VAULT_INSTALLED',false),'installer_token'=>env('INSTALLER_TOKEN',''),'backup_key'=>env('BACKUP_KEY',''),'clamav_host'=>env('CLAMAV_HOST'),'clamav_port'=>(int)env('CLAMAV_PORT',3310),'require_scan'=>(bool)env('REQUIRE_ANTIVIRUS',false),
    'mime_map'=>['jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'png'=>['image/png'],'webp'=>['image/webp'],'pdf'=>['application/pdf'],'mp4'=>['video/mp4'],'mov'=>['video/quicktime'],'txt'=>['text/plain'],'csv'=>['text/plain','text/csv']]];
