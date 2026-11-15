<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\LeadSourceType;
use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Models\RentalArea;
use App\Models\RentalBroker;
use App\Models\RentalLeadSource;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeadSourceRequestFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_saves_person_referrer_name_or_explicit_unknown(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل مصدر الإحالة',
            'phone' => '01012345681',
            'phone_country' => 'EG',
        ]);
        $source = RentalLeadSource::query()->where('name', 'الإدارة العليا')->firstOrFail();
        $area = RentalArea::query()->firstOrFail();

        $namedForm = Livewire::actingAs($manager)
            ->test(CreateRentalRequest::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => $area->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ])
            ->set('data.lead_source_id', $source->id)
            ->set('data.lead_source_referrer_name', 'نائب رئيس مجلس الإدارة');
        $namedForm
            ->assertSet('data.lead_source_referrer_name', 'نائب رئيس مجلس الإدارة')
            ->assertSet('data.lead_source_referrer_unknown', false)
            ->assertFormFieldIsVisible('lead_source_referrer_name');
        $dehydratedState = $namedForm->instance()->form->getState();
        $this->assertSame('نائب رئيس مجلس الإدارة', $dehydratedState['lead_source_referrer_name'] ?? null);
        $namedForm
            ->call('create')
            ->assertHasNoFormErrors();

        $namedRequest = RentalRequest::query()->latest('id')->firstOrFail();
        $this->assertSame('نائب رئيس مجلس الإدارة', $namedRequest->lead_source_referrer_name);
        $this->assertNull($namedRequest->lead_source_other);

        Livewire::actingAs($manager)
            ->test(CreateRentalRequest::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'area_id' => $area->id,
                'owner_user_id' => $manager->id,
                'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
            ])
            ->set('data.lead_source_id', $source->id)
            ->set('data.lead_source_referrer_unknown', true)
            ->call('create')
            ->assertHasNoFormErrors();

        $unknownRequest = RentalRequest::query()->latest('id')->firstOrFail();
        $this->assertNull($unknownRequest->lead_source_referrer_name);
        $this->assertTrue($unknownRequest->lead_source_referrer_unknown);
    }

    public function test_editing_historical_referral_with_untouched_blank_details_preserves_both_nulls(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $broker = RentalBroker::query()->create(['name' => 'وسيط مصدر تاريخي']);
        $source = RentalLeadSource::query()->where('type', LeadSourceType::PersonReferral)->firstOrFail();
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'broker_id' => $broker->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $request->forceFill([
            'lead_source_id' => $source->id,
            'lead_source_referrer_name' => null,
            'lead_source_referrer_unknown' => null,
        ])->save();

        Livewire::actingAs($manager)
            ->test(EditRentalRequest::class, ['record' => $request->id])
            ->fillForm(['notes' => 'تعديل لا يمس الإحالة'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'lead_source_id' => $source->id,
            'lead_source_referrer_name' => null,
            'lead_source_referrer_unknown' => null,
            'notes' => 'تعديل لا يمس الإحالة',
        ]);
    }

    public function test_edit_form_preserves_new_referrer_details_when_switching_source_type(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $broker = RentalBroker::query()->create(['name' => 'وسيط تغيير المصدر']);
        $other = RentalLeadSource::query()->where('type', LeadSourceType::Other)->firstOrFail();
        $referral = RentalLeadSource::query()->where('name', 'الإدارة العليا')->firstOrFail();
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'broker_id' => $broker->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            'lead_source_id' => $other->id,
            'lead_source_other' => 'إعلان غير معروف',
        ]);

        $editForm = Livewire::actingAs($manager)
            ->test(EditRentalRequest::class, ['record' => $request->id])
            ->set('data.lead_source_id', $referral->id);
        $editForm
            ->assertSet('data.lead_source_other', null)
            ->assertSet('data.lead_source_referrer_name', null)
            ->set('data.lead_source_referrer_name', 'رئيس مجلس الإدارة')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('rental_requests', [
            'id' => $request->id,
            'lead_source_id' => $referral->id,
            'lead_source_other' => null,
            'lead_source_referrer_name' => 'رئيس مجلس الإدارة',
        ]);
    }

    public function test_create_options_exclude_legacy_sources_while_edit_keeps_the_current_legacy_value(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $broker = RentalBroker::query()->create(['name' => 'وسيط مصدر تاريخي غير متاح']);
        $legacySource = RentalLeadSource::query()->where('name', 'مكالمة هاتفية')->firstOrFail();
        $otherLegacySource = RentalLeadSource::query()->where('name', 'واتساب')->firstOrFail();
        $eligibleSource = RentalLeadSource::query()->where('type', LeadSourceType::Awareness)->where('is_active', true)->firstOrFail();

        $createForm = Livewire::actingAs($manager)->test(CreateRentalRequest::class);
        $createOptions = $createForm->instance()->form->getFlatFields(withHidden: true)['lead_source_id']->getOptions();
        $this->assertArrayHasKey($eligibleSource->id, $createOptions);
        $this->assertArrayNotHasKey($legacySource->id, $createOptions);

        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'broker_id' => $broker->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $request->forceFill(['lead_source_id' => $legacySource->id])->save();

        $editForm = Livewire::actingAs($manager)->test(EditRentalRequest::class, ['record' => $request->id]);
        $editOptions = $editForm->instance()->form->getFlatFields(withHidden: true)['lead_source_id']->getOptions();
        $this->assertArrayHasKey($legacySource->id, $editOptions);
        $this->assertArrayNotHasKey($otherLegacySource->id, $editOptions);
    }
}
