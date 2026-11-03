<?php
namespace App\Services;
use Illuminate\Validation\ValidationException;
class Antivirus {
    public function scan(string $path): string {
        $host = config('vault.clamav_host');
        if (!$host) {
            if (config('vault.require_scan')) throw ValidationException::withMessages(['file'=>'Uploads are unavailable until antivirus is configured.']);
            return 'not_scanned';
        }
        $socket = @stream_socket_client('tcp://'.$host.':'.config('vault.clamav_port'),$errno,$error,10);
        if (!$socket) throw ValidationException::withMessages(['file'=>'Antivirus is unavailable. Please retry later.']);
        $file = null;
        try {
            stream_set_timeout($socket,30); $this->write($socket,"zINSTREAM\0"); $file = fopen($path,'rb');
            while (!feof($file)) { $chunk = fread($file,65536); $this->write($socket,pack('N',strlen($chunk)).$chunk); }
            $this->write($socket,pack('N',0)); $result = stream_get_contents($socket,4096);
            if (!str_ends_with(trim($result,"\0\r\n "),' OK')) throw ValidationException::withMessages(['file'=>'The file was rejected by antivirus or could not be scanned.']);
            return 'clean';
        } finally { if (is_resource($file)) fclose($file); fclose($socket); }
    }
    private function write($socket,string $data): void {
        while (strlen($data)) { $n = fwrite($socket,$data); if (!$n) throw ValidationException::withMessages(['file'=>'Antivirus connection interrupted.']); $data = substr($data,$n); }
    }
}
