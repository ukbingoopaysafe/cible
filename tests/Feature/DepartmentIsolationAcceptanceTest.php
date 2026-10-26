<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DepartmentIsolationAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_see_all_rental_requests_but_cannot_access_resale_records_or_forge_cross_department_customer(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $employee = User::factory()->create(['is_company_manager' => false]);
        $employee->departmentMemberships()->create(['department' => Department::Rental]);
        $customers = app(CustomerService::class);
        $requests = app(RequestService::class);

        $rentalCustomer = $customers->create($manager, Department::Rental, [
            'name' => 'عميل طلب قسم الإيجار',
            'phone' => '01012345680',
            'phone_country' => 'EG',
        ]);
        $rentalRequest = $requests->create($manager, Department::Rental, [
            'customer_id' => $rentalCustomer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        $customers->create($manager, Department::Resale, [
            'name' => 'سجل إعادة البيع الأول',
            'phone' => '01012345681',
            'phone_country' => 'EG',
        ]);
        $resaleCustomer = $customers->create($manager, Department::Resale, [
            'name' => 'سجل إعادة البيع المحظور',
            'phone' => '01012345682',
            'phone_country' => 'EG',
        ]);
        $resaleRequest = $requests->create($manager, Department::Resale, [
            'customer_id' => $resaleCustomer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        Livewire::actingAs($employee)
            ->test(ListRentalRequests::class)
            ->assertCanSeeTableRecords([$rentalRequest]);

        $this->actingAs($employee)
            ->get('/admin/resale-customers')
            ->assertForbidden();
        $this->actingAs($employee)
            ->get('/admin/resale-requests/'.$resaleRequest->id)
            ->assertStatus(404);
        $this->actingAs($employee)
            ->get('/admin/resale-requests/create')
            ->assertForbidden();
        $this->actingAs($employee)
            ->get('/admin/rental-requests/'.$rentalRequest->id)
            ->assertOk();

        try {
            $requests->create($employee, Department::Rental, [
                'customer_id' => $resaleCustomer->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ]);
            $this->fail('Cross-department customer identifiers must be rejected by the service.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_requests', 1);
        }
    }
}
