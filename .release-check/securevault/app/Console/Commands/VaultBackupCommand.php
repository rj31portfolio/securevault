<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class VaultBackupCommand extends Command {
    protected $signature='vault:backup {--scheduled : Honor the backup-enabled setting}';
    protected $description='Create a private, encrypted database and media backup';
    public function handle(\App\Services\VaultBackup $backup): int {
        if($this->option('scheduled') && !\App\Models\Setting::read('backup_enabled',false)) return self::SUCCESS;
        try { $this->info($backup->create()); return self::SUCCESS; } catch(\Throwable $e) { $this->error('Backup failed. Check BACKUP_KEY, disk capacity, and database/storage access.'); return self::FAILURE; }
    }
}
