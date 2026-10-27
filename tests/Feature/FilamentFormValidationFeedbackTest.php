<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalCustomers\Pages\ListRentalCustomers;
use App\Filament\Resources\RentalCustomers\RentalCustomerResource;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentFormValidationFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creation_action_fails_validation_with_invalid_phone_country(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(RentalCustomerResource::createCustomerAction()->getName(), [
                'name' => 'عميل اختبار',
                'phone' => '01011111111',
                'phone_country' => 'Ej',
            ])
            ->assertHasActionErrors(['phone_country']);
    }

    public function test_customer_creation_action_fails_validation_with_incomplete_phone(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(RentalCustomerResource::createCustomerAction()->getName(), [
                'name' => 'عميل اختبار',
                'phone' => '010111111',
                'phone_country' => 'EG',
            ])
            ->assertHasActionErrors(['phone']);
    }

    public function test_customer_creation_action_fails_validation_with_duplicate_phone(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل أول',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->callAction(RentalCustomerResource::createCustomerAction()->getName(), [
                'name' => 'عميل مكرر الهاتف',
                'phone' => '01012345678',
                'phone_country' => 'EG',
            ])
            ->assertHasActionErrors();
    }
}
