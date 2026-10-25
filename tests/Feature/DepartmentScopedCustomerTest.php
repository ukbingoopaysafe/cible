<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DepartmentScopedCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_can_exist_in_separate_department_ledgers_without_linking(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(CustomerService::class);

        $rentalCustomer = $service->create($manager, Department::Rental, [
            'name' => 'عميل الإيجار',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $resaleCustomer = $service->create($manager, Department::Resale, [
            'name' => 'عميل إعادة البيع',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);

        $this->assertNotSame($rentalCustomer::class, $resaleCustomer::class);
        $this->assertSame('rental_customers', $rentalCustomer->getTable());
        $this->assertSame('resale_customers', $resaleCustomer->getTable());
        $this->assertDatabaseCount('rental_customers', 1);
        $this->assertDatabaseCount('resale_customers', 1);
        $this->assertSame('+201012345678', $rentalCustomer->phone_e164);
        $this->assertSame($rentalCustomer->phone_e164, $resaleCustomer->phone_e164);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => $rentalCustomer::class,
            'subject_id' => $rentalCustomer->getKey(),
            'department' => Department::Rental->value,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'subject_type' => $resaleCustomer::class,
            'subject_id' => $resaleCustomer->getKey(),
            'department' => Department::Resale->value,
        ]);
    }

    public function test_duplicate_phone_is_rejected_within_one_department(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(CustomerService::class);
        $data = ['name' => 'عميل', 'phone' => '01012345678', 'phone_country' => 'EG'];
        $service->create($manager, Department::Rental, $data);

        try {
            $service->create($manager, Department::Rental, [...$data, 'name' => 'عميل آخر']);
            $this->fail('A duplicate phone number must be rejected within one department.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_customers', 1);
            $this->assertDatabaseCount('audit_events', 1);
        }
    }
}
