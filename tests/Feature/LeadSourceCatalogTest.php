<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\LeadSourceType;
use App\Filament\Resources\RentalAuditEvents\Pages\ListRentalAuditEvents;
use App\Filament\Resources\RentalLeadSources\Pages\ListRentalLeadSources;
use App\Models\AuditEvent;
use App\Models\RentalLeadSource;
use App\Models\ResaleLeadSource;
use App\Models\User;
use App\Services\LeadSourceService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LeadSourceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_typed_sources_in_each_separate_department_catalog(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(LeadSourceService::class);

        $rentalSource = $service->create($manager, Department::Rental, [
            'name' => 'ترشيح من شركة شريكة',
            'type' => LeadSourceType::PersonReferral->value,
        ]);
        $resaleSource = $service->create($manager, Department::Resale, [
            'name' => 'حملة إعادة بيع',
            'type' => LeadSourceType::Awareness->value,
        ]);

        $this->assertSame(LeadSourceType::PersonReferral, $rentalSource->type);
        $this->assertSame(LeadSourceType::Awareness, $resaleSource->type);
        $this->assertDatabaseHas('rental_lead_sources', ['id' => $rentalSource->id, 'type' => 'person_referral']);
        $this->assertDatabaseMissing('resale_lead_sources', ['name' => 'ترشيح من شركة شريكة']);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'lead_source.created',
            'department' => 'rental',
            'subject_id' => $rentalSource->id,
        ]);
    }

    public function test_manager_cannot_create_other_or_historical_lead_source_types(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(LeadSourceService::class);

        foreach ([LeadSourceType::Other, LeadSourceType::LegacyContactMethod, LeadSourceType::UnclassifiedLegacy] as $type) {
            try {
                $service->create($manager, Department::Rental, [
                    'name' => 'نوع ممنوع '.$type->value,
                    'type' => $type->value,
                ]);
                $this->fail("Creating {$type->value} through the manager catalog should fail.");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('rental_lead_sources', ['name' => 'نوع ممنوع '.$type->value]);
            }
        }
    }

    public function test_unclassified_legacy_source_must_be_classified_before_it_can_be_activated(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $source = RentalLeadSource::query()->create([
            'name' => 'مصدر قديم غير واضح',
            'type' => LeadSourceType::UnclassifiedLegacy,
            'is_active' => false,
        ]);
        $service = app(LeadSourceService::class);

        try {
            $service->setActive($manager, Department::Rental, (int) $source->id, true);
            $this->fail('An unclassified source should not be activated.');
        } catch (ValidationException) {
            $this->assertFalse((bool) $source->fresh()->is_active);
        }

        $classified = $service->classify($manager, Department::Rental, (int) $source->id, LeadSourceType::Awareness->value);
        $this->assertSame(LeadSourceType::Awareness, $classified->type);
        $this->assertFalse((bool) $classified->is_active, 'Classification must not implicitly activate the source.');

        $service->setActive($manager, Department::Rental, (int) $source->id, true);
        $this->assertTrue((bool) $source->fresh()->is_active);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'lead_source.classified',
            'department' => 'rental',
            'subject_id' => $source->id,
            'before_values->type' => 'unclassified_legacy',
            'after_values->type' => 'awareness',
        ]);
    }

    public function test_legacy_contact_methods_cannot_be_reactivated_and_built_in_other_cannot_be_deactivated(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $legacy = RentalLeadSource::query()->where('name', 'مكالمة هاتفية')->firstOrFail();
        $other = RentalLeadSource::query()->where('type', LeadSourceType::Other)->firstOrFail();
        $service = app(LeadSourceService::class);

        try {
            $service->setActive($manager, Department::Rental, (int) $legacy->id, true);
            $this->fail('A legacy contact method should remain unavailable.');
        } catch (ValidationException) {
            $this->assertFalse((bool) $legacy->fresh()->is_active);
        }

        try {
            $service->setActive($manager, Department::Rental, (int) $other->id, false);
            $this->fail('The built-in other source must remain active.');
        } catch (ValidationException) {
            $this->assertTrue((bool) $other->fresh()->is_active);
        }
    }

    public function test_only_unclassified_legacy_sources_can_be_classified(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $source = ResaleLeadSource::query()->where('type', LeadSourceType::Awareness)->firstOrFail();

        try {
            app(LeadSourceService::class)->classify($manager, Department::Resale, (int) $source->id, LeadSourceType::PersonReferral->value);
            $this->fail('A classified source must not be silently reclassified.');
        } catch (ValidationException) {
            $this->assertSame(LeadSourceType::Awareness, $source->fresh()->type);
        }
    }

    public function test_manager_can_classify_an_unclassified_source_through_the_catalog_action(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $source = RentalLeadSource::query()->create([
            'name' => 'مصدر يحتاج تصنيفًا من الواجهة',
            'type' => LeadSourceType::UnclassifiedLegacy,
            'is_active' => false,
        ]);

        Livewire::actingAs($manager)
            ->test(ListRentalLeadSources::class)
            ->callAction(TestAction::make('classify')->table($source), ['type' => LeadSourceType::PersonReferral->value])
            ->assertHasNoTableActionErrors();

        $this->assertSame(LeadSourceType::PersonReferral, $source->fresh()->type);
        $this->assertFalse((bool) $source->fresh()->is_active, 'Catalog classification must not activate the source.');
    }

    public function test_classification_audit_uses_arabic_event_and_type_labels(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $source = RentalLeadSource::query()->create([
            'name' => 'مصدر تدقيق تصنيف',
            'type' => LeadSourceType::UnclassifiedLegacy,
            'is_active' => false,
        ]);
        app(LeadSourceService::class)->classify($manager, Department::Rental, (int) $source->id, LeadSourceType::PersonReferral->value);
        $audit = AuditEvent::query()->where('event_key', 'lead_source.classified')->latest('id')->firstOrFail();

        Livewire::actingAs($manager)
            ->test(ListRentalAuditEvents::class)
            ->assertSee('تصنيف مصدر وصول قديم')
            ->assertTableColumnFormattedStateSet('after_values', 'تصنيف المصدر: إحالة من شخص، مفعل: لا', $audit);
    }
}
