<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_for_manager_with_editable_name_and_email(): void
    {
        $manager = User::factory()->create([
            'is_company_manager' => true,
            'password' => Hash::make('ManagerSecret123!'),
        ]);

        $this->actingAs($manager);
        $this->get('/admin/profile')->assertOk();

        Livewire::actingAs($manager)
            ->test(EditProfile::class)
            ->assertFormFieldEnabled('name')
            ->assertFormFieldEnabled('email')
            ->fillForm([
                'name' => 'مدير محدث',
                'email' => 'updated.manager@example.test',
                'currentPassword' => 'ManagerSecret123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $manager->fresh();
        $this->assertSame('مدير محدث', $fresh->name);
        $this->assertSame('updated.manager@example.test', $fresh->email);
    }

    public function test_profile_page_renders_for_employee_with_disabled_name_and_email(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        $this->actingAs($employee);
        $this->get('/admin/profile')->assertOk();

        Livewire::actingAs($employee)
            ->test(EditProfile::class)
            ->assertFormFieldIsDisabled('name')
            ->assertFormFieldIsDisabled('email');
    }

    public function test_employee_cannot_tamper_name_and_email_on_profile_save(): void
    {
        $employee = User::factory()->create([
            'name' => 'اسم أصلي',
            'email' => 'original@example.test',
            'is_company_manager' => false,
        ]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        Livewire::actingAs($employee)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => 'اسم معدل غير مسموح',
                'email' => 'tampered@example.test',
            ])
            ->call('save');

        $fresh = $employee->fresh();
        $this->assertSame('اسم أصلي', $fresh->name);
        $this->assertSame('original@example.test', $fresh->email);
    }

    public function test_employee_can_change_password_on_profile_page(): void
    {
        $employee = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
            'is_company_manager' => false,
        ]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        Livewire::actingAs($employee)
            ->test(EditProfile::class)
            ->fillForm([
                'currentPassword' => 'OldPassword123!',
                'password' => 'NewPassword123456!',
                'passwordConfirmation' => 'NewPassword123456!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('NewPassword123456!', $employee->fresh()->password));
    }

    public function test_user_can_upload_profile_picture_and_get_avatar_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['is_company_manager' => false]);
        $user->departmentMemberships()->create(['department' => Department::Rental]);

        $file = UploadedFile::fake()->image('avatar.jpg', 300, 300);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'avatar_url' => $file,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->avatar_url);
        $this->assertStringContainsString('avatars/', $fresh->avatar_url);
        $this->assertNotNull($fresh->getFilamentAvatarUrl());
        $this->assertStringContainsString($fresh->avatar_url, $fresh->getFilamentAvatarUrl());
    }
}
