<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthorizationBoundariesTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_manager_can_work_in_both_departments_without_memberships(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);

        $this->assertTrue($manager->canWorkInDepartment(Department::Rental));
        $this->assertTrue($manager->canWorkInDepartment(Department::Resale));
        $this->assertDatabaseCount('department_user', 0);
    }

    public function test_employee_access_is_limited_to_assigned_departments(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        $this->assertTrue($employee->canWorkInDepartment(Department::Rental));
        $this->assertFalse($employee->canWorkInDepartment(Department::Resale));
    }

    public function test_customer_service_rejects_cross_department_access(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        try {
            app(CustomerService::class)->create($employee, Department::Resale, []);
            $this->fail('An employee must not create customer records in an unassigned department.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('resale_customers', 0);
        }
    }

    public function test_request_service_rejects_cross_department_access(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);

        $this->expectException(AuthorizationException::class);

        app(RequestService::class)->create($employee, Department::Resale, []);
    }
}
