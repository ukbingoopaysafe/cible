<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use App\Services\StaffManagementService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_another_company_manager_through_service(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(StaffManagementService::class);

        $newManager = $service->createEmployee($manager, [
            'name' => 'مدير جديد',
            'email' => 'new.manager@example.test',
            'password' => 'StrongManagerPass123!',
            'is_company_manager' => true,
        ]);

        $this->assertTrue($newManager->is_company_manager);
        $this->assertDatabaseHas('users', [
            'email' => 'new.manager@example.test',
            'is_company_manager' => true,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'staff.manager_created',
            'subject_id' => $newManager->id,
        ]);
    }

    public function test_manager_can_create_manager_through_filament_action(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);

        Livewire::actingAs($manager)
            ->test(ListStaff::class)
            ->callAction(TestAction::make('createEmployee'), [
                'name' => 'مدير عبر الواجهة',
                'email' => 'ui.manager@example.test',
                'password' => 'StrongPass123!',
                'is_company_manager' => 1,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'ui.manager@example.test',
            'is_company_manager' => true,
        ]);
    }

    public function test_manager_can_disable_mfa_for_user(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $targetUser = User::factory()->create([
            'is_company_manager' => true,
        ]);
        $targetUser->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertTrue($targetUser->fresh()->hasMultiFactorAuthentication());

        $service = app(StaffManagementService::class);
        $service->disableMfa($manager, $targetUser->id);

        $this->assertFalse($targetUser->fresh()->hasMultiFactorAuthentication());
        $this->assertNull($targetUser->fresh()->getAppAuthenticationSecret());
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'staff.mfa_disabled',
            'subject_id' => $targetUser->id,
        ]);
    }

    public function test_disable_mfa_artisan_command(): void
    {
        $user = User::factory()->create(['email' => 'mfa.user@example.test']);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertTrue($user->fresh()->hasMultiFactorAuthentication());

        $this->artisan('cible:mfa:disable', ['email' => 'mfa.user@example.test'])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->hasMultiFactorAuthentication());
    }

    public function test_manager_cannot_demote_themselves(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(StaffManagementService::class);

        $this->expectException(ValidationException::class);

        $service->updateEmployee($manager, $manager->id, [
            'name' => $manager->name,
            'email' => $manager->email,
            'is_company_manager' => false,
            'departments' => [Department::Rental->value],
        ]);
    }
}
