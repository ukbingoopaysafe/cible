<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\RentalRequestStage;
use App\Enums\ResaleRequestStage;
use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Filament\Resources\ResaleRequests\Pages\CreateResaleRequest;
use App\Filament\Support\RequestFormSchema;
use App\Models\RentalArea;
use App\Models\RentalRequest;
use App\Models\ResaleArea;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequestRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_form_uses_client_category_and_furnishing_options_without_household_toggle(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل متطلبات إيجار',
            'phone' => '01012345681',
            'phone_country' => 'EG',
        ]);

        $form = Livewire::actingAs($manager)->test(CreateRentalRequest::class);
        $form
            ->assertFormFieldExists('client_category', fn ($field): bool => $field->getOptions() === [
                'family' => 'أسرة',
                'youth' => 'شباب',
                'students' => 'طلاب',
                'other' => 'أخرى',
            ])
            ->assertFormFieldDoesNotExist('household')
            ->assertFormFieldExists('finishing', fn ($field): bool => $field->getLabel() === 'نوع الفرش'
                && $field->getOptions() === [
                    'tiles' => 'بلاط',
                    'ordinary' => 'عادية',
                    'lux' => 'لوكس',
                    'super_lux' => 'سوبر لوكس',
                    'hotel' => 'فندقية',
                    'other' => 'أخرى',
                ])
            ->assertFormFieldExists('budget_declared', fn ($field): bool => $field->getLabel() === 'ميزانية العميل')
            ->assertFormFieldExists('budget_targeted', fn ($field): bool => $field->getLabel() === 'ميزانية السوق')
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
                'client_category' => 'family',
                'occupants' => 4,
                'finishing' => 'tiles',
                'budget_declared' => 20000,
                'budget_targeted' => 23000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = RentalRequest::query()->sole();
        $this->assertSame('unit_search', $request->stage->value);
        $this->assertSame('family', $request->client_category->value);
        $this->assertSame(4, $request->occupants);
        $this->assertSame('tiles', $request->finishing);
        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'budget_declared' => 20000,
            'budget_targeted' => 23000,
        ]);
    }

    public function test_sale_form_saves_purchase_requirements_and_leaves_unanswered_preferences_null(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Resale, [
            'name' => 'عميل متطلبات بيع',
            'phone' => '01012345682',
            'phone_country' => 'EG',
        ]);

        $form = Livewire::actingAs($manager)->test(CreateResaleRequest::class);
        $form
            ->assertFormFieldExists('client_category')
            ->assertFormFieldExists('occupants')
            ->assertFormFieldExists('bedrooms')
            ->assertFormFieldExists('bathrooms')
            ->assertFormFieldExists('building_year')
            ->assertFormFieldExists('elevator_required')
            ->assertFormFieldExists('water_meter')
            ->assertFormFieldExists('electricity_meter_type')
            ->assertFormFieldExists('gas_available')
            ->assertFormFieldExists('budget_declared', fn ($field): bool => $field->getLabel() === 'ميزانية العميل')
            ->assertFormFieldExists('budget_targeted', fn ($field): bool => $field->getLabel() === 'ميزانية السوق')
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => ResaleArea::query()->firstOrFail()->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
                'client_category' => 'students',
                'occupants' => 2,
                'bedrooms' => 3,
                'bathrooms' => 2,
                'building_year' => 2018,
                'elevator_required' => true,
                'water_meter' => true,
                'electricity_meter_type' => 'card',
                'gas_available' => false,
                'budget_declared' => 3000000,
                'budget_targeted' => 3200000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = ResaleRequest::query()->where('occupants', 2)->sole();
        $this->assertSame('unit_search', $request->stage->value);
        $this->assertSame('students', $request->client_category->value);
        $this->assertSame(2, $request->occupants);
        $this->assertSame(3, $request->bedrooms);
        $this->assertSame(2, $request->bathrooms);
        $this->assertSame(2018, $request->building_year);
        $this->assertTrue($request->elevator_required);
        $this->assertTrue($request->water_meter);
        $this->assertSame('card', $request->electricity_meter_type->value);
        $this->assertFalse($request->gas_available);

        $unanswered = app(RequestService::class)->create($manager, Department::Resale, [
            'customer_id' => $customer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
        ]);

        $this->assertNull($unanswered->client_category);
        $this->assertNull($unanswered->occupants);
        $this->assertNull($unanswered->bedrooms);
        $this->assertNull($unanswered->bathrooms);
        $this->assertNull($unanswered->building_year);
        $this->assertNull($unanswered->elevator_required);
        $this->assertNull($unanswered->water_meter);
        $this->assertNull($unanswered->electricity_meter_type);
        $this->assertNull($unanswered->gas_available);
    }

    public function test_rental_and_sale_share_the_four_approved_stages_and_sale_label(): void
    {
        $expected = [
            'unit_search' => 'بحث عن وحدات',
            'recommendation' => 'ترشيح',
            'viewing' => 'معاينة',
            'negotiation' => 'تفاوض',
        ];

        $this->assertSame(array_keys($expected), array_map(fn ($stage): string => $stage->value, RentalRequestStage::cases()));
        $this->assertSame(array_keys($expected), array_map(fn ($stage): string => $stage->value, ResaleRequestStage::cases()));
        $this->assertSame($expected, RequestFormSchema::stageOptions(Department::Rental));
        $this->assertSame($expected, RequestFormSchema::stageOptions(Department::Resale));
        $this->assertSame('البيع', Department::Resale->label());
    }

    public function test_editing_a_historical_rental_furnishing_keeps_it_visible_and_preserves_it(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل قيمة فرش تاريخية',
            'phone' => '01012345683',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $request->forceFill(['finishing' => 'half_finished', 'household' => false])->save();

        Livewire::actingAs($manager)
            ->test(EditRentalRequest::class, ['record' => $request->id])
            ->assertFormFieldExists('finishing', fn ($field): bool => ($field->getOptions()['half_finished'] ?? null) === 'قيمة قديمة — نصف تشطيب')
            ->fillForm(['notes' => 'تم تحديث الملاحظات فقط'])
            ->call('save')
            ->assertHasNoFormErrors();

        $request->refresh();
        $this->assertSame('half_finished', $request->finishing);
        $this->assertFalse($request->household);
        $this->assertNull($request->client_category);
    }
}
