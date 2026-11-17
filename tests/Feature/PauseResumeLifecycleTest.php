<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\RentalArea;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PauseResumeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_pausing_requires_a_reason_and_future_resume_schedule(): void
    {
        [$manager, $request, $service] = $this->rentalRequest();

        try {
            $service->pause($manager, Department::Rental, $request->id, '', now()->addDays(7)->toDateTimeString(), now()->addDays(8)->toDateTimeString());
            $this->fail('Pausing without a reason must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pause_reason', $exception->errors());
        }
    }

    public function test_pause_saves_reason_resume_time_and_first_follow_up_then_manual_resume_clears_the_plan(): void
    {
        [$manager, $request, $service] = $this->rentalRequest();
        $resumeAt = now()->addDays(14)->startOfMinute();
        $resumeFollowUpAt = $resumeAt->copy()->addDay();

        $service->pause(
            $manager,
            Department::Rental,
            $request->id,
            'العميل مسافر',
            $resumeAt->toDateTimeString(),
            $resumeFollowUpAt->toDateTimeString(),
        );

        $paused = $request->fresh();
        $this->assertSame(RequestStatus::Paused, $paused->status);
        $this->assertSame('العميل مسافر', $paused->pause_reason);
        $this->assertSame($resumeAt->toDateTimeString(), $paused->resume_at->toDateTimeString());
        $this->assertSame($resumeFollowUpAt->toDateTimeString(), $paused->resume_follow_up_at->toDateTimeString());
        $this->assertNull($paused->next_follow_up_at);

        $manualFollowUp = now()->addDays(2)->startOfMinute();
        $service->resume($manager, Department::Rental, $request->id, $manualFollowUp->toDateTimeString());

        $resumed = $request->fresh();
        $this->assertSame(RequestStatus::Active, $resumed->status);
        $this->assertSame($manualFollowUp->toDateTimeString(), $resumed->next_follow_up_at->toDateTimeString());
        $this->assertNull($resumed->paused_at);
        $this->assertNull($resumed->pause_reason);
        $this->assertNull($resumed->resume_at);
        $this->assertNull($resumed->resume_follow_up_at);
    }

    public function test_closing_a_paused_request_clears_its_pending_resume_plan(): void
    {
        [$manager, $request, $service] = $this->rentalRequest();
        $service->pause(
            $manager,
            Department::Rental,
            $request->id,
            'انتظار قرار',
            now()->addDays(10)->toDateTimeString(),
            now()->addDays(11)->toDateTimeString(),
        );

        $service->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);

        $closed = $request->fresh();
        $this->assertSame(RequestStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
        $this->assertNull($closed->pause_reason);
        $this->assertNull($closed->resume_at);
        $this->assertNull($closed->resume_follow_up_at);
    }

    /** @return array{User, RentalRequest, RequestService} */
    private function rentalRequest(): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل إيقاف مؤقت',
            'phone' => '01012345684',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        return [$manager, $request, app(RequestService::class)];
    }
}
