<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\User;
use App\Services\RentalAreaService;
use App\Services\StaffManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class RentalAreaPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_grant_and_revoke_rental_area_creation_for_staff(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $staffService = app(StaffManagementService::class);
        $employee = $staffService->createEmployee($manager, [
            'name' => 'موظف الإيجار',
            'email' => 'rental.staff@example.test',
            'password' => 'a-long-test-password',
            'departments' => [Department::Rental->value],
            'can_add_rental_areas' => true,
        ]);

        $areaService = app(RentalAreaService::class);
        $existingAreaCount = RentalArea::query()->count();
        $area = $areaService->create($employee, ['governorate' => 'القاهرة', 'name' => 'منطقة اختبار']);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => $area::class,
            'subject_id' => $area->getKey(),
            'event_key' => 'rental_area.created',
        ]);

        $staffService->updateEmployee($manager, (int) $employee->getKey(), [
            'name' => $employee->name,
            'email' => $employee->email,
            'departments' => [Department::Rental->value],
            'can_add_rental_areas' => false,
        ]);

        try {
            $areaService->create($employee->fresh(), ['governorate' => 'الجيزة', 'name' => 'منطقة أخرى']);
            $this->fail('A revoked employee must not add a rental area.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('rental_areas', $existingAreaCount + 1);
        }
    }

    public function test_duplicate_area_is_rejected_and_does_not_create_an_audit_event(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(RentalAreaService::class);
        $existingAreaCount = RentalArea::query()->count();
        $service->create($manager, ['governorate' => 'القاهرة', 'name' => 'التجمع التجريبي']);

        try {
            $service->create($manager, ['governorate' => 'القاهرة', 'name' => ' التجمع التجريبي ']);
            $this->fail('Duplicate areas must be rejected after trimming whitespace.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_areas', $existingAreaCount + 1);
            $this->assertDatabaseCount('audit_events', 1);
        }
    }
}
