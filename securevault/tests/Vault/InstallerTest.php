<?php
namespace Tests\Vault;
use Tests\TestCase;
class InstallerTest extends TestCase {
    public function test_installer_requires_a_private_claim_token_and_cannot_skip_steps(): void {
        config(['vault.installed'=>false,'vault.installer_token'=>str_repeat('a',64)]);
        if(is_file(storage_path('app/installed.lock'))) $this->markTestSkipped('Installed local environment.');
        $this->get('/install')->assertOk()->assertSee('STEP 1 OF 8');
        $this->post('/install',['token'=>'wrong'])->assertForbidden();
        $this->post('/install',['token'=>str_repeat('a',64)])->assertRedirect('/install')->assertSessionHas('install_step',2);
        $this->get('/install')->assertSee('Server requirements');
        $this->withSession(['install_step'=>7,'installer_authorized'=>false])->post('/install')->assertForbidden();
    }
    public function test_uninstalled_app_redirects_to_installer(): void {
        config(['vault.installed'=>false]); if(is_file(storage_path('app/installed.lock'))) $this->markTestSkipped('Installed local environment.');
        $this->get('/login')->assertRedirect('/install');
    }
}
