<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\LeadSourceType;
use App\Models\AuditEvent;
use App\Models\RentalArea;
use App\Models\RentalBroker;
use App\Models\RentalLeadSource;
use App\Models\ResaleArea;
use App\Models\ResaleBroker;
use App\Models\ResaleLeadSource;
use App\Models\User;
use App\Services\RequestService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadSourceRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_person_referral_creation_requires_exactly_one_name_or_explicit_unknown_in_both_departments(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);

        foreach ([Department::Rental, Department::Resale] as $department) {
            $source = $this->source($department, LeadSourceType::PersonReferral);

            $this->assertInvalidCreate($manager, $department, [
                'lead_source_id' => $source->id,
            ]);
            $this->assertInvalidCreate($manager, $department, [
                'lead_source_id' => $source->id,
                'lead_source_referrer_name' => 'شخص مُحيل',
                'lead_source_referrer_unknown' => true,
            ]);

            $namedRequest = $this->createRequest($manager, $department, [
                'lead_source_id' => $source->id,
                'lead_source_referrer_name' => 'رئيس مجلس الإدارة',
            ]);
            $this->assertSame('رئيس مجلس الإدارة', $namedRequest->lead_source_referrer_name);
            $this->assertNull($namedRequest->lead_source_referrer_unknown);

            $unknownRequest = $this->createRequest($manager, $department, [
                'lead_source_id' => $source->id,
                'lead_source_referrer_unknown' => true,
            ]);
            $this->assertNull($unknownRequest->lead_source_referrer_name);
            $this->assertTrue($unknownRequest->lead_source_referrer_unknown);
        }
    }

    public function test_source_changes_keep_only_submitted_values_compatible_with_the_new_type(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(RequestService::class);

        foreach ([Department::Rental, Department::Resale] as $department) {
            $other = $this->source($department, LeadSourceType::Other);
            $personReferral = $this->source($department, LeadSourceType::PersonReferral);
            $awareness = $this->source($department, LeadSourceType::Awareness);
            $request = $this->createRequest($manager, $department, [
                'lead_source_id' => $other->id,
                'lead_source_other' => 'وصف سابق',
            ]);

            $service->updateDetails($manager, $department, $request->id, [
                'lead_source_id' => $personReferral->id,
                'lead_source_referrer_name' => 'الإدارة العليا',
            ]);
            $request->refresh();
            $this->assertSame('الإدارة العليا', $request->lead_source_referrer_name);
            $this->assertNull($request->lead_source_referrer_unknown);
            $this->assertNull($request->lead_source_other);

            $service->updateDetails($manager, $department, $request->id, [
                'lead_source_id' => $other->id,
                'lead_source_other' => 'مصدر موصوف حديثًا',
            ]);
            $request->refresh();
            $this->assertSame('مصدر موصوف حديثًا', $request->lead_source_other);
            $this->assertNull($request->lead_source_referrer_name);
            $this->assertNull($request->lead_source_referrer_unknown);

            $service->updateDetails($manager, $department, $request->id, [
                'lead_source_id' => $awareness->id,
            ]);
            $request->refresh();
            $this->assertNull($request->lead_source_other);
            $this->assertNull($request->lead_source_referrer_name);
            $this->assertNull($request->lead_source_referrer_unknown);

            $service->updateDetails($manager, $department, $request->id, [
                'lead_source_id' => $personReferral->id,
                'lead_source_referrer_unknown' => true,
            ]);
            $request->refresh();
            $this->assertTrue($request->lead_source_referrer_unknown);
            $this->assertNull($request->lead_source_referrer_name);
        }
    }

    public function test_unchanged_historical_referral_nulls_and_inactive_sources_survive_unrelated_edits(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(RequestService::class);
        $source = RentalLeadSource::query()->where('type', LeadSourceType::PersonReferral)->firstOrFail();
        $request = $this->createRequest($manager, Department::Rental);

        $request->forceFill([
            'lead_source_id' => $source->id,
            'lead_source_referrer_name' => null,
            'lead_source_referrer_unknown' => null,
        ])->save();
        $source->is_active = false;
        $source->save();

        $service->updateDetails($manager, Department::Rental, $request->id, ['notes' => 'تعديل غير متعلق بالإحالة']);
        $request->refresh();

        $this->assertSame($source->id, $request->lead_source_id);
        $this->assertNull($request->lead_source_referrer_name);
        $this->assertNull($request->lead_source_referrer_unknown);
        $this->assertSame('تعديل غير متعلق بالإحالة', $request->notes);
    }

    public function test_explicit_referrer_changes_require_one_value_and_audit_redacts_the_name(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $service = app(RequestService::class);
        $source = RentalLeadSource::query()->where('type', LeadSourceType::PersonReferral)->firstOrFail();
        $request = $this->createRequest($manager, Department::Rental, [
            'lead_source_id' => $source->id,
            'lead_source_referrer_unknown' => true,
        ]);

        try {
            $service->updateDetails($manager, Department::Rental, $request->id, [
                'lead_source_referrer_name' => null,
                'lead_source_referrer_unknown' => false,
            ]);
            $this->fail('Clearing both referral values must fail validation.');
        } catch (ValidationException) {
            $this->assertTrue($request->fresh()->lead_source_referrer_unknown);
        }

        $service->updateDetails($manager, Department::Rental, $request->id, [
            'lead_source_referrer_name' => 'اسم لا يظهر في سجل التدقيق',
            'lead_source_referrer_unknown' => false,
        ]);

        $audit = AuditEvent::query()
            ->where('event_key', 'request.details_updated')
            ->where('subject_id', $request->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('[تغير النص؛ المحتوى محفوظ في سجل الطلب]', $audit->after_values['lead_source_referrer_name']);
        $this->assertStringNotContainsString('اسم لا يظهر في سجل التدقيق', json_encode($audit->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame('اسم لا يظهر في سجل التدقيق', $request->fresh()->lead_source_referrer_name);
    }

    public function test_broker_first_request_keeps_awareness_source_blank(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $request = $this->createRequest($manager, Department::Rental);

        $this->assertNotNull($request->broker_id);
        $this->assertNull($request->lead_source_id);
        $this->assertNull($request->lead_source_referrer_name);
        $this->assertNull($request->lead_source_referrer_unknown);
    }

    private function assertInvalidCreate(User $manager, Department $department, array $sourceData): void
    {
        try {
            $this->createRequest($manager, $department, $sourceData);
            $this->fail('Invalid referral attribution must fail validation.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function createRequest(User $manager, Department $department, array $attributes = []): Model
    {
        $areaId = match ($department) {
            Department::Rental => RentalArea::query()->firstOrFail()->id,
            Department::Resale => ResaleArea::query()->firstOrFail()->id,
        };
        $brokerClass = $department === Department::Rental ? RentalBroker::class : ResaleBroker::class;
        $brokerId = $brokerClass::query()->create(['name' => 'وسيط الطلب '.$department->value.' '.uniqid()])->id;

        return app(RequestService::class)->create($manager, $department, array_replace([
            'area_id' => $areaId,
            'broker_id' => $brokerId,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ], $attributes));
    }

    private function source(Department $department, LeadSourceType $type): RentalLeadSource|ResaleLeadSource
    {
        $class = $department === Department::Rental ? RentalLeadSource::class : ResaleLeadSource::class;
        $query = $class::query()->where('type', $type)->where('is_active', true);

        if ($type === LeadSourceType::Awareness) {
            $query->where('requires_details', false);
        }

        return $query->firstOrFail();
    }
}
