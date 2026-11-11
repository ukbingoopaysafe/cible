<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Filament\Support\AuditEventResource;
use App\Models\AuditEvent;
use App\Models\RentalArea;
use App\Models\RentalBroker;
use App\Models\RentalFieldInspection;
use App\Models\ResaleArea;
use App\Models\ResaleBroker;
use App\Models\ResaleFieldInspection;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RecommendationService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class RecommendationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_linked_to_recommendation_requires_inspection_and_audits_result_without_feedback(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل معاينة',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $service = app(RecommendationService::class);
        $recommendation = $service->createRecommendation($manager, Department::Rental, $request->id, [
            'unit_code' => 'UNIT-101',
            'currency' => 'EGP',
        ]);
        $service->updateRecommendationStatus($manager, Department::Rental, $recommendation->id, 'shared');

        try {
            $service->scheduleViewing($manager, Department::Rental, $request->id, [
                'recommendation_id' => $recommendation->id,
                'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            ]);
            $this->fail('A linked viewing must require a field inspection first.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_viewings', 0);
        }

        $service->recordInspection($manager, Department::Rental, $recommendation->id, [
            'field_representative_name' => 'مندوب ميداني',
            'inspected_at' => now()->subMinute()->toDateTimeString(),
            'photo_match' => 'partly_matches',
            'contact_role' => 'owner',
            'contact_name' => 'مالك الوحدة',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $viewing = $service->scheduleViewing($manager, Department::Rental, $request->id, [
            'recommendation_id' => $recommendation->id,
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'field_representative_name' => 'مندوب معاينة',
        ]);

        $service->recordViewingResult($manager, Department::Rental, $viewing->id, [
            'result' => 'unsuitable',
            'customer_feedback' => 'الموقع بعيد عن العمل',
            'rejection_reason' => 'الموقع غير مناسب',
        ]);

        $this->assertDatabaseHas('rental_viewings', [
            'id' => $viewing->id,
            'result' => 'unsuitable',
            'rejection_reason' => 'الموقع غير مناسب',
        ]);
        $audit = AuditEvent::query()->where('event_key', 'viewing.result_recorded')->firstOrFail();
        $this->assertSame('unsuitable', $audit->after_values['to']);
        $this->assertArrayNotHasKey('customer_feedback', $audit->after_values);
        $this->assertArrayNotHasKey('rejection_reason', $audit->after_values);
    }

    public function test_inspection_records_owner_contact_snapshot_and_privacy_safe_audit_for_both_departments(): void
    {
        foreach ([Department::Rental, Department::Resale] as $department) {
            [$manager, $request, $recommendation, $service] = $this->createInspectionContext($department);
            $inspection = $service->recordInspection($manager, $department, $recommendation->id, $this->inspectionInput());
            $table = $department->value.'_field_inspections';

            $this->assertDatabaseHas($table, [
                'id' => $inspection->id,
                'recommendation_id' => $recommendation->id,
                'contact_role' => 'owner',
                'contact_name' => 'مالك الوحدة',
                'contact_phone_e164' => '+201012345678',
                'contact_phone_country' => 'EG',
                'linked_broker_id' => null,
                'notes' => 'ملاحظات فحص',
            ]);

            $audit = AuditEvent::query()
                ->where('event_key', 'field_inspection.recorded')
                ->where('department', $department->value)
                ->where('subject_id', $inspection->id)
                ->sole();

            $this->assertSame([
                'contact_role' => 'owner',
                'broker_link_confirmed' => false,
                'linked_broker_id' => null,
            ], $audit->after_values);
            $this->assertArrayNotHasKey('contact_name', $audit->after_values);
            $this->assertArrayNotHasKey('phone', $audit->after_values);
            $this->assertArrayNotHasKey('notes', $audit->after_values);
            $this->assertSame($request->id, $inspection->recommendation->request_id);
        }
    }

    public function test_inspection_requires_a_valid_contact_role_name_and_phone(): void
    {
        [$manager, , $recommendation, $service] = $this->createInspectionContext(Department::Rental);
        $invalidInputs = [
            ['contact_role' => null],
            ['contact_role' => 'customer'],
            ['contact_name' => '   '],
            ['phone' => 'not-a-phone'],
            ['phone_country' => 'XX'],
            ['link_request_broker' => true],
        ];

        foreach ($invalidInputs as $overrides) {
            try {
                $service->recordInspection($manager, Department::Rental, $recommendation->id, array_replace($this->inspectionInput(), $overrides));
                $this->fail('Invalid contact input should be rejected: '.json_encode($overrides));
            } catch (ValidationException) {
                $this->assertDatabaseCount('rental_field_inspections', 0);
            }
        }
    }

    public function test_new_inspection_service_rejects_legacy_defects_payload(): void
    {
        [$manager, , $recommendation, $service] = $this->createInspectionContext(Department::Rental);

        try {
            $service->recordInspection($manager, Department::Rental, $recommendation->id, [
                ...$this->inspectionInput(),
                'observed_defects' => 'لا تقبل كعيوب جديدة',
            ]);
            $this->fail('New inspection input must reject observed_defects.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_field_inspections', 0);
        }
    }

    public function test_inspection_audit_metadata_uses_arabic_labels_and_role_values(): void
    {
        $record = new AuditEvent([
            'event_key' => 'field_inspection.recorded',
            'department' => Department::Rental->value,
        ]);
        $formatter = new ReflectionMethod(AuditEventResource::class, 'formatValues');
        $formatter->setAccessible(true);

        $formatted = $formatter->invoke(null, [
            'contact_role' => 'broker',
            'broker_link_confirmed' => true,
            'linked_broker_id' => 42,
        ], $record);

        $this->assertSame('صفة جهة التواصل: وسيط، تم تأكيد ربط وسيط الطلب: نعم، معرّف وسيط الطلب: 42', $formatted);
    }

    public function test_inspection_can_link_only_to_the_confirmed_broker_on_its_department_request(): void
    {
        foreach ([Department::Rental, Department::Resale] as $department) {
            [$manager, $request, $recommendation, $service] = $this->createInspectionContext($department);
            $brokerClass = $department === Department::Rental ? RentalBroker::class : ResaleBroker::class;
            $broker = $brokerClass::query()->create(['name' => 'وسيط الفحص '.$department->value]);
            $request->forceFill(['broker_id' => $broker->id])->save();

            $inspection = $service->recordInspection($manager, $department, $recommendation->id, [
                ...$this->inspectionInput(),
                'contact_role' => 'broker',
                'contact_name' => 'وسيط الوحدة',
                'link_request_broker' => true,
                'shown_request_broker_id' => $broker->id,
            ]);

            $inspectionClass = $department === Department::Rental ? RentalFieldInspection::class : ResaleFieldInspection::class;
            $this->assertSame($broker->id, $inspection->linked_broker_id);
            $this->assertInstanceOf($brokerClass, $inspectionClass::query()->findOrFail($inspection->id)->linkedBroker);

            $audit = AuditEvent::query()
                ->where('event_key', 'field_inspection.recorded')
                ->where('department', $department->value)
                ->where('subject_id', $inspection->id)
                ->sole();
            $this->assertSame([
                'contact_role' => 'broker',
                'broker_link_confirmed' => true,
                'linked_broker_id' => $broker->id,
            ], $audit->after_values);

            $request->forceFill(['broker_id' => null])->save();
            $broker->update(['name' => 'اسم محدث بعد الفحص']);
            $inspection->refresh();
            $this->assertSame($broker->id, $inspection->linked_broker_id);
            $this->assertSame('وسيط الوحدة', $inspection->contact_name);
            $this->assertSame('+201012345678', $inspection->contact_phone_e164);

            $broker->delete();
            $inspection->refresh();
            $this->assertNull($inspection->linked_broker_id);
            $this->assertSame('وسيط الوحدة', $inspection->contact_name);
            $this->assertSame('+201012345678', $inspection->contact_phone_e164);
        }
    }

    public function test_owner_contact_cannot_be_linked_to_request_broker(): void
    {
        [$manager, $request, $recommendation, $service] = $this->createInspectionContext(Department::Rental);
        $broker = RentalBroker::query()->create(['name' => 'وسيط الطلب']);
        $request->forceFill(['broker_id' => $broker->id])->save();

        try {
            $service->recordInspection($manager, Department::Rental, $recommendation->id, [
                ...$this->inspectionInput(),
                'contact_role' => 'owner',
                'link_request_broker' => true,
                'shown_request_broker_id' => $broker->id,
            ]);
            $this->fail('Owner contacts cannot be linked to a request broker.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_field_inspections', 0);
        }
    }

    public function test_inspection_rejects_link_if_request_broker_changed_after_form_confirmation(): void
    {
        foreach ([Department::Rental, Department::Resale] as $department) {
            [$manager, $request, $recommendation, $service] = $this->createInspectionContext($department);
            $brokerClass = $department === Department::Rental ? RentalBroker::class : ResaleBroker::class;
            $shownBroker = $brokerClass::query()->create(['name' => 'الوسيط المعروض '.$department->value]);
            $currentBroker = $brokerClass::query()->create(['name' => 'الوسيط الحالي '.$department->value]);
            $request->forceFill(['broker_id' => $currentBroker->id])->save();

            try {
                $service->recordInspection($manager, $department, $recommendation->id, [
                    ...$this->inspectionInput(),
                    'contact_role' => 'broker',
                    'link_request_broker' => true,
                    'shown_request_broker_id' => $shownBroker->id,
                ]);
                $this->fail('A stale broker confirmation must be rejected.');
            } catch (ValidationException) {
                $this->assertSame(0, ($department === Department::Rental ? RentalFieldInspection::query() : ResaleFieldInspection::query())->count());
            }
        }
    }

    private function createInspectionContext(Department $department): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, $department, [
            'name' => 'عميل الفحص '.$department->value,
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $areaClass = $department === Department::Rental ? RentalArea::class : ResaleArea::class;
        $request = app(RequestService::class)->create($manager, $department, [
            'customer_id' => $customer->id,
            'area_id' => $areaClass::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $service = app(RecommendationService::class);
        $recommendation = $service->createRecommendation($manager, $department, $request->id, [
            'unit_code' => 'UNIT-'.$department->value,
            'currency' => 'EGP',
        ]);
        $service->updateRecommendationStatus($manager, $department, $recommendation->id, 'shared');

        return [$manager, $request, $recommendation, $service];
    }

    private function inspectionInput(): array
    {
        return [
            'field_representative_name' => 'مندوب الفحص',
            'inspected_at' => now()->subMinute()->toDateTimeString(),
            'photo_match' => 'matches',
            'contact_role' => 'owner',
            'contact_name' => 'مالك الوحدة',
            'phone' => '01012345678',
            'phone_country' => 'EG',
            'link_request_broker' => false,
            'notes' => 'ملاحظات فحص',
        ];
    }
}
