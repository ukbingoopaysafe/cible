<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\StaffManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAuditRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_audit_records_change_flags_without_emails_or_passwords(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(StaffManagementService::class);
        $employee = $service->createEmployee($manager, [
            'name' => 'موظف',
            'email' => 'staff.old@example.test',
            'password' => 'old-password-long-enough',
            'departments' => [Department::Rental->value],
        ]);

        $service->updateEmployee($manager, (int) $employee->id, [
            'name' => 'موظف محدث',
            'email' => 'staff.new@example.test',
            'password' => 'new-password-long-enough',
            'departments' => [Department::Rental->value],
        ]);

        $update = AuditEvent::query()->where('event_key', 'staff.employee_updated')->firstOrFail();
        $serialized = json_encode([$update->before_values, $update->after_values], JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('staff.old@example.test', $serialized);
        $this->assertStringNotContainsString('staff.new@example.test', $serialized);
        $this->assertStringNotContainsString('old-password-long-enough', $serialized);
        $this->assertStringNotContainsString('new-password-long-enough', $serialized);
        $this->assertTrue($update->after_values['email_changed']);
        $this->assertTrue($update->after_values['password_changed']);
    }
}
