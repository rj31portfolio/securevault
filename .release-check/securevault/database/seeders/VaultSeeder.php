<?php
namespace Database\Seeders;
use App\Models\{Role,Permission,Category,Setting};
use Illuminate\Database\Seeder;
class VaultSeeder extends Seeder {
    public function run(): void {
        $admin = Role::firstOrCreate(['name'=>'super_admin']); $user = Role::firstOrCreate(['name'=>'user']);
        foreach (['files.upload','files.view','files.manage','users.manage','settings.manage'] as $name) {
            $p = Permission::firstOrCreate(['name'=>$name]); $admin->permissions()->syncWithoutDetaching([$p->id]);
            if (in_array($name,['files.upload','files.view'],true)) $user->permissions()->syncWithoutDetaching([$p->id]);
        }
        foreach (['Chat Screenshots','WhatsApp Screenshots','Personal Images','Important Documents','Videos','Business Documents','Other Files'] as $name) Category::firstOrCreate(['name'=>$name],['enabled'=>true,'is_screenshot'=>str_contains($name,'Screenshots'),'allowed_extensions'=>['jpg','jpeg','png','webp','pdf','mp4','mov'],'max_upload_mb'=>100]);
        foreach (['site_name'=>'SecureVault','registration_enabled'=>true,'email_verification_required'=>true,'admin_approval_required'=>false,'max_upload_mb'=>100,'allowed_extensions'=>['jpg','jpeg','png','webp','pdf','mp4','mov'],'storage_limit_mb'=>10240,'user_quota_mb'=>1024,'session_timeout'=>60,'watermark_enabled'=>true,'watermark_text'=>'PRIVATE • SECUREVAULT','file_retention_days'=>0,'deleted_file_retention_days'=>30,'log_retention_days'=>365,'backup_enabled'=>false,'backup_retention_days'=>14] as $key=>$value) if (!Setting::find($key)) Setting::put($key,$value);
    }
}
