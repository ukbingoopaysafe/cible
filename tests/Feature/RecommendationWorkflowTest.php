<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RecommendationService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
            'observed_defects' => 'تحتاج صيانة بسيطة',
        ]);
        $viewing = $service->scheduleViewing($manager, Department::Rental, $request->id, [
            'recommendation_id' => $recommendation->id,
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'field_representative_name' => 'مندوب معاينة',
        ]);

        $service->recordViewingResult($manager, Department::Rental, $viewing->id, [
            'result' => 'not_interested',
            'customer_feedback' => 'الموقع بعيد عن العمل',
            'rejection_reason' => 'الموقع غير مناسب',
        ]);

        $this->assertDatabaseHas('rental_viewings', [
            'id' => $viewing->id,
            'result' => 'not_interested',
            'rejection_reason' => 'الموقع غير مناسب',
        ]);
        $audit = \App\Models\AuditEvent::query()->where('event_key', 'viewing.result_recorded')->firstOrFail();
        $this->assertSame('not_interested', $audit->after_values['to']);
        $this->assertArrayNotHasKey('customer_feedback', $audit->after_values);
        $this->assertArrayNotHasKey('rejection_reason', $audit->after_values);
    }
}
