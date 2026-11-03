<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Resources\RentalBrokers\Pages\ListRentalBrokers;
use App\Filament\Resources\RentalCustomers\Pages\ListRentalCustomers;
use App\Filament\Support\PhoneField;
use App\Models\User;
use App\Services\CustomerService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhoneFieldWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_field_can_bind_a_named_phone_and_country_pair(): void
    {
        $field = PhoneField::make(
            label: 'هاتف البائع',
            required: false,
            phoneField: 'seller_phone',
            countryField: 'seller_phone_country',
        );

        $components = $field->getDefaultChildComponents();

        $this->assertSame('seller_phone_country', $components[0]->getName());
        $this->assertSame('seller_phone', $components[1]->getName());
    }

    public function test_phone_field_renders_country_flags_as_trusted_html_without_helper_text(): void
    {
        $field = PhoneField::make();
        $countrySelect = $field->getDefaultChildComponents()[0];
        $helperText = new \ReflectionProperty($field, 'helperTextSchema');

        $this->assertTrue($countrySelect->isHtmlAllowed());
        $this->assertNull($helperText->getValue($field));
        $this->assertSame('fi-phone-country-select', $countrySelect->getExtraAttributes()['class']);
        $this->assertSame('top', $countrySelect->getPosition());
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertStringContainsString('.fi-phone-country-flag', $theme);
        $this->assertStringContainsString('max-height: 11rem', $theme);
    }

    public function test_customer_can_be_created_with_a_saudi_number(): void
    {
        Livewire::actingAs($this->manager())
            ->test(ListRentalCustomers::class)
            ->callAction(TestAction::make('createCustomer'), [
                'name' => 'عميل سعودي',
                'phone' => '0512345678',
                'phone_country' => 'SA',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_customers', [
            'name' => 'عميل سعودي',
            'phone_e164' => '+966512345678',
            'phone_country' => 'SA',
        ]);
    }

    public function test_customer_can_be_created_with_an_other_international_number(): void
    {
        Livewire::actingAs($this->manager())
            ->test(ListRentalCustomers::class)
            ->callAction(TestAction::make('createCustomer'), [
                'name' => 'عميل ألماني',
                'phone' => '+4915112345678',
                'phone_country' => 'ZZ',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_customers', [
            'name' => 'عميل ألماني',
            'phone_e164' => '+4915112345678',
            'phone_country' => 'DE',
        ]);
    }

    public function test_broker_can_be_created_without_a_phone_number(): void
    {
        Livewire::actingAs($this->manager())
            ->test(ListRentalBrokers::class)
            ->callAction(TestAction::make('createBroker'), [
                'name' => 'وسيط بدون هاتف',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('rental_brokers', [
            'name' => 'وسيط بدون هاتف',
            'phone_e164' => null,
            'phone_country' => null,
        ]);
    }

    public function test_customer_edit_form_shows_the_national_number_for_the_stored_country(): void
    {
        $manager = $this->manager();
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل تعديل الهاتف',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->mountAction(TestAction::make('editCustomer')->table($customer))
            ->assertSchemaStateSet([
                'phone' => '01012345678',
                'phone_country' => 'EG',
            ]);
    }

    public function test_customer_edit_form_keeps_other_numbers_in_international_format(): void
    {
        $manager = $this->manager();
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل تعديل دولي',
            'phone' => '+4915112345678',
            'phone_country' => 'ZZ',
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalCustomers::class)
            ->mountAction(TestAction::make('editCustomer')->table($customer))
            ->assertSchemaStateSet([
                'phone' => '+4915112345678',
                'phone_country' => 'ZZ',
            ]);
    }

    private function manager(): User
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $manager;
    }
}
