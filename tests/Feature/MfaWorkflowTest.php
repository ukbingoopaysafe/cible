<?php

namespace Tests\Feature;

use App\Auth\SafeAppAuthentication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MfaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_totp_secret_is_encrypted_and_accepts_a_current_code(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $secret = 'JBSWY3DPEHPK3PXP';
        $manager->saveAppAuthenticationSecret($secret);

        $storedSecret = DB::table('users')
            ->where('id', $manager->id)
            ->value('app_authentication_secret');

        $this->assertIsString($storedSecret);
        $this->assertNotSame($secret, $storedSecret);
        $this->assertSame($secret, $manager->fresh()->getAppAuthenticationSecret());

        $appAuthentication = app(SafeAppAuthentication::class);
        $this->actingAs($manager);

        $this->assertTrue($appAuthentication->verifyCode(
            $appAuthentication->getCurrentCode($manager, $secret),
            $secret,
        ));
    }

    public function test_recovery_code_is_encrypted_and_can_only_be_used_once(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $recoveryCode = 'recover-this-code-once';
        $appAuthentication = app(SafeAppAuthentication::class);

        $appAuthentication->saveRecoveryCodes($manager, [$recoveryCode]);

        $storedCodes = DB::table('users')
            ->where('id', $manager->id)
            ->value('app_authentication_recovery_codes');

        $this->assertIsString($storedCodes);
        $this->assertStringNotContainsString($recoveryCode, $storedCodes);
        $this->assertCount(1, $manager->fresh()->getAppAuthenticationRecoveryCodes());
        $this->assertCount(1, User::query()->whereKey($manager)->lockForUpdate()->firstOrFail()->getAppAuthenticationRecoveryCodes());
        $this->assertTrue($appAuthentication->verifyRecoveryCode($recoveryCode, $manager));
        $this->assertFalse($appAuthentication->verifyRecoveryCode($recoveryCode, $manager));
        $this->assertSame([], $manager->fresh()->getAppAuthenticationRecoveryCodes());
    }
}
