<?php
namespace App\Console\Commands;
use App\Models\{VaultFile,StorageUsage,Setting};
use App\Services\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Storage};
class SecurityPurgeCommand extends Command {
    protected $signature='vault:security-purge {--execute : Execute retention purge; otherwise show counts} {--logs : Also purge audit logs older than configured retention}';
    protected $description='Server security operator only: purge retained media and optionally old audit logs';
    public function handle(): int {
        $cutoff=now()->subDays((int)Setting::read('deleted_file_retention_days',30)); $q=VaultFile::onlyTrashed()->where('deleted_at','<',$cutoff);
        $this->info($q->count().' retained media records are eligible. Records remain; only stored bytes are purged.');
        if(!$this->option('execute')) return self::SUCCESS;
        // Only OS-level security operators can invoke this command. No HTTP route exposes retention.
        foreach($q->where('scan_status','!=','purged')->cursor() as $file) {
            DB::transaction(function() use($file) {
                Setting::where('key','storage_limit_mb')->lockForUpdate()->firstOrFail();
                $locked=VaultFile::onlyTrashed()->whereKey($file->id)->lockForUpdate()->firstOrFail(); if($locked->scan_status==='purged') return;
                if(!Storage::disk('local')->delete($locked->disk_path)) throw new \RuntimeException('Private file purge failed');
                $usage=StorageUsage::where('user_id',$locked->user_id)->lockForUpdate()->firstOrFail(); $usage->bytes_used=max(0,$usage->bytes_used-$locked->size); $usage->file_count=max(0,$usage->file_count-1); $usage->save();
                $locked->scan_status='purged'; $locked->save(); Audit::record('file_purged','Security operator purged retained bytes for '.$locked->id);
            });
        }
        if($this->option('logs')) {
            $before=now()->subDays((int)Setting::read('log_retention_days',365));
            foreach(['file_views','user_activities','login_histories','security_logs','email_verification_logs'] as $table) DB::table($table)->where('created_at','<',$before)->delete();
            Audit::record('log_retention','Server security operator applied audit retention.',null,'security_logs');
        }
        $this->info('Retention purge complete.'); return self::SUCCESS;
    }
}
