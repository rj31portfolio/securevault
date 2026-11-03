<?php
namespace Tests\Vault;
use App\Models\{User,Role,Category,Setting,VaultFile,StorageUsage};
use App\Services\FileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Hash,Notification,Password,Storage,URL};
use Tests\TestCase;
class VaultSecurityTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); config(['vault.installed'=>true]); $this->seed(\Database\Seeders\VaultSeeder::class); Storage::fake('local'); Notification::fake(); }
    private function member(string $role='user',string $status='active',bool $verified=true): User {
        $user=new User(['name'=>'Test Member','email'=>bin2hex(random_bytes(6)).'@example.com','password'=>'Strong!Pass1234']);
        $user->role_id=Role::where('name',$role)->sole()->id; $user->status=$status; $user->email_verified_at=$verified?now():null; $user->terms_accepted_at=now(); $user->save(); StorageUsage::create(['user_id'=>$user->id]); return $user->refresh();
    }
    private function signIn(User $user): static { return $this->actingAs($user)->withSession(['session_version'=>$user->session_version,'vault_last_activity'=>time()]); }
    private function storedFile(User $user): VaultFile { return app(FileStorage::class)->upload($user,UploadedFile::fake()->image('memory.png'),Category::first(),['title'=>'Private memory']); }
    public function test_registration_uses_email_verification_and_no_phone(): void {
        $this->post('/register',['name'=>'New Member','email'=>'new@example.com','password'=>'Strong!Pass1234','password_confirmation'=>'Strong!Pass1234','terms'=>'1'])->assertRedirect('/login');
        $user=User::where('email','new@example.com')->sole(); $this->assertNull($user->email_verified_at); $this->assertTrue(Hash::check('Strong!Pass1234',$user->password));
        Notification::assertSentTo($user,\Illuminate\Auth\Notifications\VerifyEmail::class); $this->assertDatabaseHas('user_activities',['user_id'=>$user->id,'type'=>'registration']);
        $this->post('/register',['name'=>'Duplicate','email'=>$user->email,'password'=>'Strong!Pass1234','password_confirmation'=>'Strong!Pass1234','terms'=>'1'])->assertRedirect('/login'); $this->assertSame(1,User::where('email',$user->email)->count());
    }
    public function test_verification_requires_a_valid_signed_link_and_expires(): void {
        $user=$this->member(verified:false); $this->signIn($user);
        $url=URL::temporarySignedRoute('verification.verify',now()->addMinutes(5),['id'=>$user->id,'hash'=>sha1($user->email)]);
        $this->get($url.'&tampered=1')->assertForbidden(); $this->get($url)->assertRedirect('/dashboard'); $this->assertNotNull($user->fresh()->email_verified_at);
        $expired=URL::temporarySignedRoute('verification.verify',now()->subMinute(),['id'=>$user->id,'hash'=>sha1($user->email)]); $this->get($expired)->assertForbidden();
    }
    public function test_login_rotates_session_and_records_history(): void {
        $user=$this->member(); $this->post('/login',['email'=>$user->email,'password'=>'wrong'])->assertSessionHasErrors('email'); $this->assertGuest();
        $this->post('/login',['email'=>$user->email,'password'=>'Strong!Pass1234'])->assertRedirect('/dashboard'); $this->assertAuthenticatedAs($user); $this->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('login_histories',['user_id'=>$user->id,'type'=>'login']); $this->assertDatabaseHas('login_histories',['user_id'=>$user->id,'type'=>'failed_login']);
    }
    public function test_unverified_and_pending_accounts_cannot_enter_vault(): void {
        $user=$this->member(verified:false); $this->signIn($user)->get('/files')->assertRedirect(route('verification.notice'));
        $pending=$this->member(status:'pending'); $this->signIn($pending)->get('/files')->assertForbidden();
    }
    public function test_password_reset_is_email_based_and_single_use(): void {
        $user=$this->member(); $this->post('/forgot-password',['email'=>$user->email])->assertSessionHas('status'); Notification::assertSentTo($user,\Illuminate\Auth\Notifications\ResetPassword::class);
        $this->post('/forgot-password',['email'=>'missing@example.com'])->assertSessionHas('status');
        $token=Password::createToken($user); $payload=['email'=>$user->email,'token'=>$token,'password'=>'Another!Pass1234','password_confirmation'=>'Another!Pass1234'];
        $this->post('/reset-password',$payload)->assertRedirect('/login'); $this->assertTrue(Hash::check('Another!Pass1234',$user->fresh()->password)); $this->assertSame(2,$user->fresh()->session_version);
        $this->post('/reset-password',$payload)->assertSessionHasErrors('email');
    }
    public function test_upload_is_private_and_ownership_is_enforced_on_every_request(): void {
        $owner=$this->member(); $other=$this->member(); $this->signIn($owner);
        $this->post('/files',['title'=>'My screenshot','category_id'=>Category::first()->id,'file'=>UploadedFile::fake()->image('shot.png')])->assertRedirect();
        $file=VaultFile::sole(); Storage::disk('local')->assertExists($file->disk_path); $this->assertStringStartsWith('vault/',$file->disk_path);
        $this->get(route('files.show',$file))->assertOk(); $response=$this->get(route('files.content',$file))->assertOk(); $this->assertStringStartsWith('inline',$response->headers->get('Content-Disposition'));
        $this->signIn($other)->get(route('files.show',$file))->assertNotFound(); $this->get(route('files.content',$file))->assertNotFound(); $this->assertDatabaseHas('security_logs',['user_id'=>$other->id,'type'=>'unauthorized_file']);
    }
    public function test_members_cannot_delete_edit_download_share_or_access_admin(): void {
        $user=$this->member(); $file=$this->storedFile($user); $this->signIn($user);
        $this->delete(route('admin.files.delete',$file))->assertForbidden(); $this->put('/files/'.$file->id,['title'=>'Changed'])->assertStatus(405);
        $this->get('/files/'.$file->id.'/download')->assertNotFound(); $this->get('/files/'.$file->id.'/share')->assertNotFound(); $this->get('/admin/users')->assertForbidden();
        $this->assertDatabaseHas('files',['id'=>$file->id,'deleted_at'=>null]);
    }
    public function test_executable_and_mime_mismatched_uploads_are_rejected(): void {
        $user=$this->member(); $this->signIn($user);
        $this->post('/files',['title'=>'Bad','category_id'=>Category::first()->id,'file'=>UploadedFile::fake()->createWithContent('fake.png','<?php echo 1;')])->assertSessionHasErrors('file');
        $this->post('/files',['title'=>'Bad','category_id'=>Category::first()->id,'file'=>UploadedFile::fake()->createWithContent('run.php','<?php echo 1;')])->assertSessionHasErrors('file'); $this->assertSame(0,VaultFile::count());
    }
    public function test_quota_and_category_restrictions_reject_upload_without_storage_leak(): void {
        $user=$this->member(); $user->storageUsage()->update(['bytes_used'=>1048576]); Setting::put('user_quota_mb',1); $this->signIn($user);
        $this->post('/files',['title'=>'Too much','category_id'=>Category::first()->id,'file'=>UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('file');
        $user->storageUsage()->update(['bytes_used'=>0]); $category=Category::first(); $category->update(['enabled'=>false]);
        $this->post('/files',['title'=>'Disabled','category_id'=>$category->id,'file'=>UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('file'); $this->assertSame([],Storage::disk('local')->allFiles());
    }
    public function test_only_admin_deletes_files_and_account_retention_is_enforced(): void {
        $member=$this->member(); $file=$this->storedFile($member); $admin=$this->member('super_admin'); $this->signIn($admin);
        $this->delete(route('admin.users.delete',$member),['confirm_email'=>$member->email])->assertStatus(422);
        $this->delete(route('admin.files.delete',$file))->assertRedirect(route('admin.files')); $this->assertSoftDeleted('files',['id'=>$file->id]); Storage::disk('local')->assertExists($file->disk_path);
        $this->delete(route('admin.users.delete',$member),['confirm_email'=>$member->email])->assertRedirect(route('admin.users')); $this->assertSoftDeleted('users',['id'=>$member->id]); $this->assertDatabaseHas('user_activities',['type'=>'account_deleted']);
    }
    public function test_minimum_file_retention_prevents_admin_deletion(): void {
        $member=$this->member(); $file=$this->storedFile($member); Setting::put('file_retention_days',30); $this->signIn($this->member('super_admin'))->delete(route('admin.files.delete',$file))->assertSessionHasErrors('file'); $this->assertDatabaseHas('files',['id'=>$file->id,'deleted_at'=>null]);
    }
    public function test_suspension_invalidates_existing_sessions(): void {
        $user=$this->member(); $admin=$this->member('super_admin'); $version=$user->session_version; $this->signIn($admin)->patch(route('admin.users.status',$user),['status'=>'suspended'])->assertRedirect();
        $this->actingAs($user->fresh())->withSession(['session_version'=>$version])->get('/files')->assertRedirect('/login');
    }
    public function test_password_change_requires_current_password_and_revokes_sessions(): void {
        $user=$this->member(); $this->signIn($user); $data=['current_password'=>'wrong','password'=>'Another!Pass1234','password_confirmation'=>'Another!Pass1234'];
        $this->put('/password',$data)->assertSessionHasErrors('current_password'); $data['current_password']='Strong!Pass1234'; $this->put('/password',$data)->assertRedirect('/login'); $this->assertGuest(); $this->assertSame(2,$user->fresh()->session_version);
    }
    public function test_settings_encrypt_smtp_and_never_render_saved_password(): void {
        $this->signIn($this->member('super_admin')); $smtp=['host'=>'smtp.example.com','port'=>587,'username'=>'mailer','password'=>'TopSecret!123','encryption'=>'tls','from_address'=>'vault@example.com','from_name'=>'Vault'];
        $this->put(route('admin.smtp'),$smtp)->assertRedirect(); $this->assertStringNotContainsString($smtp['password'],Setting::find('smtp')->value); $this->assertSame($smtp['password'],Setting::read('smtp')['password']);
        $this->get(route('admin.settings'))->assertOk()->assertDontSee($smtp['password']);
    }
    public function test_audit_logs_have_no_update_or_delete_routes(): void {
        $this->signIn($this->member('super_admin')); $this->get('/admin/activity')->assertOk(); $this->delete('/admin/activity/1')->assertNotFound(); $this->put('/admin/activity/1',['description'=>'tamper'])->assertNotFound();
    }
    public function test_all_primary_views_render_for_member_and_admin(): void {
        $user=$this->member(); $this->signIn($user); foreach(['/dashboard','/files','/upload','/categories','/activity','/profile'] as $url) $this->get($url)->assertOk();
        $admin=$this->member('super_admin'); $this->signIn($admin); foreach(['/admin/users','/admin/files','/admin/categories','/admin/activity','/admin/settings','/admin/users/'.$user->id] as $url) $this->get($url)->assertOk();
    }
    public function test_installer_is_disabled_after_installation(): void { $this->get('/install')->assertNotFound(); $this->post('/install')->assertNotFound(); }
    public function test_protected_media_supports_ranges_and_guest_redirects(): void {
        $user=$this->member(); $file=$this->storedFile($user); $this->signIn($user);
        $this->withHeaders(['Range'=>'bytes=0-9'])->get(route('files.content',$file))->assertStatus(206)->assertHeader('Content-Length','10');
        $this->post('/logout')->assertRedirect('/login'); $this->get(route('files.content',$file))->assertRedirect('/login');
    }
    public function test_expired_reset_token_is_rejected(): void {
        $user=$this->member(); $token=Password::createToken($user); DB::table('password_reset_tokens')->where('email',$user->email)->update(['created_at'=>now()->subHours(2)]);
        $this->post('/reset-password',['email'=>$user->email,'token'=>$token,'password'=>'Another!Pass1234','password_confirmation'=>'Another!Pass1234'])->assertSessionHasErrors('email'); $this->assertTrue(Hash::check('Strong!Pass1234',$user->fresh()->password));
    }
    public function test_clamav_required_policy_fails_closed_when_unconfigured(): void {
        config(['vault.require_scan'=>true,'vault.clamav_host'=>null]); $user=$this->member(); $this->signIn($user);
        $this->post('/files',['title'=>'Needs scan','category_id'=>Category::first()->id,'file'=>UploadedFile::fake()->image('a.png')])->assertSessionHasErrors('file'); $this->assertSame(0,VaultFile::count());
    }
    public function test_retained_bytes_remain_until_security_operator_purge(): void {
        $user=$this->member(); $file=$this->storedFile($user); $admin=$this->member('super_admin'); $this->signIn($admin)->delete(route('admin.files.delete',$file))->assertRedirect();
        $this->assertGreaterThan(0,$user->storageUsage()->first()->bytes_used); DB::table('files')->where('id',$file->id)->update(['deleted_at'=>now()->subDays(40)]);
        $this->artisan('vault:security-purge')->assertSuccessful(); Storage::disk('local')->assertExists($file->disk_path);
        $this->artisan('vault:security-purge',['--execute'=>true])->assertSuccessful(); Storage::disk('local')->assertMissing($file->disk_path); $this->assertSame(0,(int)$user->storageUsage()->first()->bytes_used); $this->assertDatabaseHas('files',['id'=>$file->id,'scan_status'=>'purged']);
    }
    public function test_backup_is_encrypted_and_decrypts_with_separate_key(): void {
        $user=$this->member(); $file=$this->storedFile($user); $key=base64_encode(random_bytes(32)); config(['vault.backup_key'=>$key]);
        $path=app(\App\Services\VaultBackup::class)->create(); $zip=$path.'.test.zip'; $bad=$path.'.tampered'; $failed=$path.'.failed.zip';
        try {
            $this->assertSame('SVB1',file_get_contents($path,false,null,0,4));
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tools/decrypt-backup.php'),$path,$zip],base_path(),['BACKUP_KEY'=>$key]); $process->mustRun();
            $archive=new \ZipArchive; $this->assertTrue($archive->open($zip)); $this->assertNotFalse($archive->locateName('database.ndjson')); $this->assertNotFalse($archive->locateName('private/'.$file->disk_path)); $archive->close();
            $bytes=file_get_contents($path); $bytes[20]=chr(ord($bytes[20])^1); file_put_contents($bad,$bytes);
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,base_path('tools/decrypt-backup.php'),$bad,$failed],base_path(),['BACKUP_KEY'=>$key]); $process->run(); $this->assertFalse($process->isSuccessful(),$process->getOutput().$process->getErrorOutput()); $this->assertFileDoesNotExist($failed);
        } finally { foreach([$path,$zip,$bad,$failed] as $temp) if(is_file($temp)) unlink($temp); }
    }
}
