<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\RentalRequest;
use App\Models\RentalRequestCloseReason;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestOutcomeClosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsuccessful_closure_requires_a_classification_and_preserves_historical_text(): void
    {
        [$manager, $request, $service] = $this->rentalRequest();
        $request->forceFill(['outcome_reason_text' => 'سبب محفوظ من السجل القديم'])->save();

        try {
            $service->close($manager, Department::Rental, $request->id, [
                'outcome' => 'unsuccessful',
                'outcome_reason_text' => 'نص جديد لا يجب حفظه',
            ]);
            $this->fail('An unsuccessful close must require an active classification.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('outcome_reason_id', $exception->errors());
        }

        $reason = RentalRequestCloseReason::query()->where('is_active', true)->firstOrFail();
        $service->close($manager, Department::Rental, $request->id, [
            'outcome' => 'unsuccessful',
            'outcome_reason_id' => $reason->id,
            'outcome_notes' => 'ملاحظة إضافية اختيارية',
            'outcome_reason_text' => 'نص جديد لا يجب حفظه',
        ]);

        $closed = $request->fresh();
        $this->assertSame($reason->id, $closed->outcome_reason_id);
        $this->assertSame('سبب محفوظ من السجل القديم', $closed->outcome_reason_text);
        $this->assertSame('ملاحظة إضافية اختيارية', $closed->outcome_notes);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_successful_closure_ignores_new_legacy_text_without_overwriting_history(): void
    {
        [$manager, $request, $service] = $this->rentalRequest();
        $request->forceFill(['outcome_reason_text' => 'نص تاريخي'])->save();

        $service->close($manager, Department::Rental, $request->id, [
            'outcome' => 'successful',
            'outcome_reason_text' => 'نص جديد لا يجب حفظه',
        ]);

        $closed = $request->fresh();
        $this->assertSame('نص تاريخي', $closed->outcome_reason_text);
        $this->assertNull($closed->outcome_reason_id);
        $this->assertNotNull($closed->closed_at);
    }

    /** @return array{User, RentalRequest, RequestService} */
    private function rentalRequest(): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل تصنيف الإغلاق',
            'phone' => '01012345685',
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
