<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalCustomers\Pages\ListRentalCustomers;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\ResaleRequests\Pages\ListResaleRequests;
use App\Models\RentalArea;
use App\Models\RentalCustomer;
use App\Models\RentalRequest;
use App\Models\ResaleArea;
use App\Models\ResaleCustomer;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Support\RecordCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecordCodeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_code_helper_generates_correct_prefixes_and_padding(): void
    {
        $this->assertSame('Rr-12', RecordCode::request(Department::Rental, 12));
        $this->assertSame('Sr-12', RecordCode::request(Department::Resale, 12));
        $this->assertSame('Rc-45', RecordCode::customer(Department::Rental, 45));
        $this->assertSame('Sc-45', RecordCode::customer(Department::Resale, 45));

        $this->assertSame('Rr-01', RecordCode::request(Department::Rental, 1));
        $this->assertSame('Sr-01', RecordCode::request(Department::Resale, 1));
        $this->assertSame('Rc-01', RecordCode::customer(Department::Rental, 1));
        $this->assertSame('Sc-01', RecordCode::customer(Department::Resale, 1));

        $this->assertSame('Rr-12', Department::Rental->requestCode(12));
        $this->assertSame('Sr-12', Department::Resale->requestCode(12));
        $this->assertSame('Rc-45', Department::Rental->customerCode(45));
        $this->assertSame('Sc-45', Department::Resale->customerCode(45));
    }

    public function test_record_code_parser_resolves_expected_formats_and_rejects_alien_prefixes(): void
    {
        $this->assertSame(12, RecordCode::parseId('Rr-12', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(12, RecordCode::parseId('rR-12', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(12, RecordCode::parseId('Rr-000012', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(12, RecordCode::parseId('000012', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(12, RecordCode::parseId('12', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(1, RecordCode::parseId('Rr-01', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertSame(1, RecordCode::parseId('01', Department::Rental, RecordCode::TYPE_REQUEST));

        // Alien prefix in Rental Request search must return false
        $this->assertFalse(RecordCode::parseId('Sr-12', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertFalse(RecordCode::parseId('Rc-12', Department::Rental, RecordCode::TYPE_REQUEST));
        $this->assertFalse(RecordCode::parseId('Sc-12', Department::Rental, RecordCode::TYPE_REQUEST));

        // Alien prefix in Resale Request search must return false
        $this->assertFalse(RecordCode::parseId('Rr-12', Department::Resale, RecordCode::TYPE_REQUEST));

        // Customer codes
        $this->assertSame(45, RecordCode::parseId('Rc-45', Department::Rental, RecordCode::TYPE_CUSTOMER));
        $this->assertFalse(RecordCode::parseId('Sc-45', Department::Rental, RecordCode::TYPE_CUSTOMER));
    }

    public function test_rental_requests_table_searchable_by_full_code_padded_and_raw_id(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = RentalCustomer::query()->forceCreate([
            'name' => 'عميل تجريبي',
            'phone_e164' => '+201011111111',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->create(['name' => 'منطقة إيجار', 'governorate' => 'القاهرة']);
        $targetRequest = RentalRequest::query()->forceCreate([
            'id' => 12,
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => 'active',
            'stage' => 'intake',
            'next_follow_up_at' => now()->addDay(),
        ]);
        $otherRequest = RentalRequest::query()->forceCreate([
            'id' => 99,
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => 'active',
            'stage' => 'intake',
            'next_follow_up_at' => now()->addDay(),
        ]);

        $this->actingAs($manager);

        // 1. Search by full code: Rr-12
        Livewire::test(ListRentalRequests::class)
            ->searchTable('Rr-12')
            ->assertCanSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);

        // 2. Search by legacy padded digits: 000012
        Livewire::test(ListRentalRequests::class)
            ->searchTable('000012')
            ->assertCanSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);

        // 3. Search by raw id: 12
        Livewire::test(ListRentalRequests::class)
            ->searchTable('12')
            ->assertCanSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);

        // 4. Search by alien code (Resale request code Sr-12) must NOT return the rental request
        Livewire::test(ListRentalRequests::class)
            ->searchTable('Sr-12')
            ->assertCanNotSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);
    }

    public function test_resale_requests_table_searchable_and_does_not_return_when_searching_rental_code(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = ResaleCustomer::query()->forceCreate([
            'name' => 'عميل بيع',
            'phone_e164' => '+201022222222',
            'phone_country' => 'EG',
        ]);
        $area = ResaleArea::query()->create(['name' => 'منطقة بيع', 'governorate' => 'القاهرة']);
        $targetRequest = ResaleRequest::query()->forceCreate([
            'id' => 12,
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => 'active',
            'stage' => 'intake',
            'next_follow_up_at' => now()->addDay(),
        ]);
        $otherRequest = ResaleRequest::query()->forceCreate([
            'id' => 88,
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'area_id' => $area->id,
            'contact_party' => 'customer',
            'status' => 'active',
            'stage' => 'intake',
            'next_follow_up_at' => now()->addDay(),
        ]);

        $this->actingAs($manager);

        // Search by full resale code: Sr-12
        Livewire::test(ListResaleRequests::class)
            ->searchTable('Sr-12')
            ->assertCanSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);

        // Search by rental code: Rr-12 must NOT return the resale request
        Livewire::test(ListResaleRequests::class)
            ->searchTable('Rr-12')
            ->assertCanNotSeeTableRecords([$targetRequest])
            ->assertCanNotSeeTableRecords([$otherRequest]);
    }

    public function test_rental_customers_table_searchable_by_code_and_ignores_resale_customer_code(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $targetCustomer = RentalCustomer::query()->forceCreate([
            'id' => 45,
            'name' => 'عميل إيجار',
            'phone_e164' => '+201033333333',
            'phone_country' => 'EG',
        ]);
        $otherCustomer = RentalCustomer::query()->forceCreate([
            'id' => 77,
            'name' => 'عميل آخر',
            'phone_e164' => '+201044444444',
            'phone_country' => 'EG',
        ]);

        $this->actingAs($manager);

        // 1. Search by Rc-45
        Livewire::test(ListRentalCustomers::class)
            ->searchTable('Rc-45')
            ->assertCanSeeTableRecords([$targetCustomer])
            ->assertCanNotSeeTableRecords([$otherCustomer]);

        // 2. Search by alien code Sc-45 must NOT return rental customer
        Livewire::test(ListRentalCustomers::class)
            ->searchTable('Sc-45')
            ->assertCanNotSeeTableRecords([$targetCustomer]);
    }
}
