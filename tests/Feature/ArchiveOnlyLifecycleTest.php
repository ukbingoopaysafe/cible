<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\AreaArchiveService;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ArchiveOnlyLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_archive_preserves_history_and_blocks_new_requests_until_restored(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل محفوظ',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $requestService = app(RequestService::class);
        $request = $requestService->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $customers = app(CustomerService::class);

        try {
            $customers->archive($manager, Department::Rental, $customer->id);
            $this->fail('A customer with an open request must remain available for work.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('rental_customers', ['id' => $customer->id, 'deleted_at' => null]);
        }

        $requestService->close($manager, Department::Rental, $request->id, [
            'outcome' => 'unsuccessful',
            'outcome_reason_text' => 'أُغلق الطلب قبل الأرشفة',
        ]);
        $customers->archive($manager, Department::Rental, $customer->id);

        $this->assertSoftDeleted('rental_customers', ['id' => $customer->id]);
        $this->assertSame('عميل محفوظ', $request->fresh()->customer->name);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'customer.archived',
            'subject_id' => $customer->id,
            'department' => Department::Rental->value,
        ]);

        try {
            $requestService->create($manager, Department::Rental, [
                'customer_id' => $customer->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ]);
            $this->fail('An archived customer must not be selectable for a new request.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_requests', 1);
        }

        try {
            $customers->create($manager, Department::Rental, [
                'name' => 'عميل جديد',
                'phone' => '01012345678',
                'phone_country' => 'EG',
            ]);
            $this->fail('An archived customer phone remains reserved to avoid implicit merging.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_customers', 1);
        }

        $customers->restore($manager, Department::Rental, $customer->id);
        $this->assertNotSoftDeleted('rental_customers', ['id' => $customer->id]);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'customer.restored',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_area_archive_keeps_existing_request_relation_and_blocks_new_use(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل منطقة',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->firstOrFail();
        $requestService = app(RequestService::class);
        $request = $requestService->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => $area->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $areas = app(AreaArchiveService::class);

        $areas->archive($manager, Department::Rental, $area->id);

        $this->assertSoftDeleted('rental_areas', ['id' => $area->id]);
        $this->assertSame($area->name, $request->fresh()->area->name);

        try {
            $requestService->create($manager, Department::Rental, [
                'customer_id' => $customer->id,
                'area_id' => $area->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ]);
            $this->fail('An archived area must not be used for a new request.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_requests', 1);
        }

        $areas->restore($manager, Department::Rental, $area->id);
        $this->assertNotSoftDeleted('rental_areas', ['id' => $area->id]);
    }

    public function test_employee_cannot_archive_customers_or_areas(): void
    {
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $customer = app(CustomerService::class)->create($employee, Department::Rental, [
            'name' => 'عميل الموظف',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->firstOrFail();

        try {
            app(CustomerService::class)->archive($employee, Department::Rental, $customer->id);
            $this->fail('Employees must not archive customers.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('rental_customers', ['id' => $customer->id, 'deleted_at' => null]);
        }

        $this->expectException(AuthorizationException::class);
        app(AreaArchiveService::class)->archive($employee, Department::Rental, $area->id);
    }

    public function test_resale_areas_can_be_archived_and_restored_by_a_manager(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $area = ResaleArea::query()->firstOrFail();
        $areas = app(AreaArchiveService::class);

        $areas->archive($manager, Department::Resale, $area->id);
        $this->assertSoftDeleted('resale_areas', ['id' => $area->id]);

        $areas->restore($manager, Department::Resale, $area->id);
        $this->assertNotSoftDeleted('resale_areas', ['id' => $area->id]);
    }
}
