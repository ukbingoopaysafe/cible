<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_response_has_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_manager_can_open_both_department_customer_pages(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->actingAs($manager)->get('/admin/rental-customers')->assertOk();
        $this->actingAs($manager)->get('/admin/resale-customers')->assertOk();
    }

    public function test_company_manager_without_mfa_can_access_panel_when_mfa_is_optional(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);

        $this->actingAs($manager)
            ->get('/admin/rental-customers')
            ->assertOk();
    }

    public function test_employee_can_open_only_the_department_pages_the_panel_exposes(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        $this->actingAs($employee)->get('/admin/rental-customers')->assertOk();
        $this->actingAs($employee)->get('/admin/resale-customers')->assertForbidden();
    }
}
