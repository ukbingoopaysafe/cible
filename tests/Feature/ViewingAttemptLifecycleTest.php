<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\ViewingAppointmentStatus;
use App\Models\RentalArea;
use App\Models\RentalCustomer;
use App\Models\RentalRequest;
use App\Models\RentalViewing;
use App\Models\ResaleArea;
use App\Models\ResaleCustomer;
use App\Models\ResaleRequest;
use App\Models\ResaleViewing;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RecommendationService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ViewingAttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewing_can_be_created_without_a_time_then_completed_and_given_a_result(): void
    {
        [$manager, $request, $service] = $this->rentalContext();
        $viewing = $service->scheduleViewing($manager, Department::Rental, $request->id, []);

        $this->assertSame(ViewingAppointmentStatus::PendingSchedule, $viewing->appointment_status);
        $this->assertNull($viewing->scheduled_at);

        try {
            $service->recordViewingResult($manager, Department::Rental, $viewing->id, ['result' => 'awaiting_customer_reply']);
            $this->fail('A pending appointment cannot receive a viewing result.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('viewing_id', $exception->errors());
        }

        $service->setViewingSchedule($manager, Department::Rental, $viewing->id, now()->addDays(2)->toDateTimeString());
        $completed = $service->completeViewing($manager, Department::Rental, $viewing->id);
        $this->assertSame(ViewingAppointmentStatus::Completed, $completed->appointment_status);

        $service->recordViewingResult($manager, Department::Rental, $viewing->id, ['result' => 'awaiting_customer_reply']);
        $this->assertDatabaseHas('rental_viewings', [
            'id' => $viewing->id,
            'appointment_status' => 'completed',
            'result' => 'awaiting_customer_reply',
        ]);
    }

    public function test_postponement_preserves_the_old_attempt_and_creates_a_linked_attempt(): void
    {
        [$manager, $request, $service] = $this->rentalContext();
        $original = $service->scheduleViewing($manager, Department::Rental, $request->id, [
            'scheduled_at' => now()->addDays(2)->toDateTimeString(),
            'field_representative_name' => 'مندوب المعاينة',
        ]);

        $replacement = $service->postponeViewing($manager, Department::Rental, $original->id, [
            'scheduled_at' => now()->addDays(5)->toDateTimeString(),
        ]);

        $this->assertSame(ViewingAppointmentStatus::Postponed, $original->fresh()->appointment_status);
        $this->assertNull($original->fresh()->result);
        $this->assertSame($original->id, $replacement->rescheduled_from_id);
        $this->assertSame(ViewingAppointmentStatus::Scheduled, $replacement->appointment_status);
        $this->assertSame('مندوب المعاينة', $replacement->field_representative_name);
        $this->assertDatabaseCount('rental_viewings', 2);
    }

    public function test_cancellation_is_an_appointment_state_and_not_a_sale_result(): void
    {
        [$manager, $request, $service] = $this->resaleContext();
        $viewing = $service->scheduleViewing($manager, Department::Resale, $request->id, []);

        $cancelled = $service->cancelViewing($manager, Department::Resale, $viewing->id);

        $this->assertSame(ViewingAppointmentStatus::Cancelled, $cancelled->appointment_status);
        $this->assertNull($cancelled->result);

        try {
            $service->recordViewingResult($manager, Department::Resale, $viewing->id, ['result' => 'cancelled']);
            $this->fail('Cancellation cannot be stored as a viewing result.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('viewing_id', $exception->errors());
        }
    }

    /** @return array{User, RentalRequest, RecommendationService} */
    private function rentalContext(): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل معاينة إيجار', 'phone' => '01012345701', 'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        return [$manager, $request, app(RecommendationService::class)];
    }

    /** @return array{User, ResaleRequest, RecommendationService} */
    private function resaleContext(): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Resale, [
            'name' => 'عميل معاينة بيع', 'phone' => '01012345702', 'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Resale, [
            'customer_id' => $customer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        return [$manager, $request, app(RecommendationService::class)];
    }
}
