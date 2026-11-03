<?php
namespace App\Services;
use App\Models\{User,VaultFile,Category,StorageUsage,Setting};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class FileStorage {
    public function upload(User $user,UploadedFile $upload,Category $category,array $data): VaultFile {
        $ext = strtolower($upload->getClientOriginalExtension()); $mime = $upload->getMimeType();
        $map = config('vault.mime_map'); $allowed = Setting::read('allowed_extensions',['jpg','jpeg','png','webp','pdf','mp4','mov']);
        if (!$category->enabled || !in_array($ext,$allowed,true) || !in_array($ext,$category->allowed_extensions,true) || !in_array($mime,$map[$ext] ?? [],true)) $this->reject('File extension, MIME type, or category is not allowed.');
        $size = $upload->getSize(); $limit = min((int)$category->max_upload_mb,(int)Setting::read('max_upload_mb',100))*1048576;
        if (!$size || $size > $limit) $this->reject('File exceeds the permitted upload size or is empty.');
        if (str_starts_with($mime,'image/') && !@getimagesize($upload->getRealPath())) $this->reject('The image is invalid.');
        // Reject executable markers even in otherwise allowed image/document uploads.
        $handle = fopen($upload->getRealPath(),'rb'); $tail = '';
        try { while (!feof($handle)) { $chunk = $tail.fread($handle,65536); if (preg_match('/<\?(?:php|=)|<script\b/i',$chunk)) $this->reject('Embedded executable content is prohibited.'); $tail = substr($chunk,-16); } } finally { fclose($handle); }
        $scan = app(Antivirus::class)->scan($upload->getRealPath()); $path = 'vault/'.Str::uuid().'/'.Str::random(48).'.bin';
        try {
            return DB::transaction(function () use ($user,$upload,$category,$data,$size,$mime,$ext,$scan,$path) {
                // A global lock serializes storage accounting across users; user lock protects individual quota.
                Setting::where('key','storage_limit_mb')->lockForUpdate()->firstOrFail();
                $owner=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                if($owner->status!=='active') $this->reject('Your account is not currently permitted to upload.');
                $usage = StorageUsage::firstOrCreate(['user_id'=>$user->id]);
                if ($usage->bytes_used+$size > (int)Setting::read('user_quota_mb',1024)*1048576) $this->reject('Your storage quota has been reached.');
                if (StorageUsage::sum('bytes_used')+$size > (int)Setting::read('storage_limit_mb',10240)*1048576) $this->reject('The vault storage limit has been reached.');
                $stream = fopen($upload->getRealPath(),'rb');
                try { if (!Storage::disk('local')->put($path,$stream,['visibility'=>'private'])) throw new \RuntimeException('Private storage write failed.'); } finally { fclose($stream); }
                $file = VaultFile::create(['user_id'=>$user->id,'category_id'=>$category->id,'title'=>$data['title'],'description'=>$data['description'] ?? null,'disk_path'=>$path,'mime'=>$mime,'extension'=>$ext,'size'=>$size,'sha256'=>hash_file('sha256',$upload->getRealPath()),'scan_status'=>$scan]);
                $usage->increment('bytes_used',$size); $usage->increment('file_count'); Audit::record('file_upload','Uploaded file '.$file->id,$user); return $file;
            },3);
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
    }
    public function delete(VaultFile $file,User $actor): void {
        DB::transaction(function () use ($file,$actor) {
            Setting::where('key','storage_limit_mb')->lockForUpdate()->firstOrFail();
            $locked = VaultFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
            $days = (int)Setting::read('file_retention_days',0);
            if ($days && $locked->created_at->addDays($days)->isFuture()) throw ValidationException::withMessages(['file'=>'This file is still within the configured minimum retention period.']);
            // Delete is logical. A privileged security command later purges bytes after retention.
            $locked->delete(); Audit::record('file_deleted','Administrator moved file '.$locked->id.' to retained deletion.',$actor);
        });
    }
    private function reject(string $message): never { throw ValidationException::withMessages(['file'=>$message]); }
}
