<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Enums\RequestStatus;
use App\Models\RentalArea;
use App\Models\RentalLeadSource;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\AftercareService;
use App\Services\ContactActivityService;
use App\Services\CustomerService;
use App\Services\DealService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_can_pause_resume_and_close_unsuccessfully_with_reason(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل اختبار',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $area = RentalArea::query()->firstOrFail();
        $service = app(RequestService::class);

        $request = $service->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => $area->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            'budget_declared' => 25000,
        ]);

        $this->assertSame('intake', $request->stage->value);
        $this->assertSame('EGP', $request->budget_currency);
        $this->assertDatabaseHas('rental_request_stage_histories', [
            'request_id' => $request->id,
            'from_stage' => null,
            'to_stage' => 'intake',
        ]);

        $activity = app(ContactActivityService::class)->record($manager, Department::Rental, $request->id, [
            'channel' => 'phone',
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'result' => 'تم التأكد من الميزانية',
            'next_action' => 'إرسال خيارات مناسبة',
        ]);
        $this->assertSame($customer->name, $activity->contact_name_snapshot);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'contact.activity_recorded',
            'subject_id' => $request->id,
        ]);

        $service->changeStage($manager, Department::Rental, $request->id, 'negotiation');
        $service->pause($manager, Department::Rental, $request->id, 'انتظار رد العميل');
        $this->assertSame(RequestStatus::Paused, $request->fresh()->status);
        $this->assertNull($request->fresh()->next_follow_up_at);

        $service->resume($manager, Department::Rental, $request->id, now()->addDays(2)->toDateTimeString());
        $this->assertSame(RequestStatus::Active, $request->fresh()->status);
        $this->assertSame('negotiation', $request->fresh()->stage->value);

        $service->close($manager, Department::Rental, $request->id, [
            'outcome' => 'unsuccessful',
            'outcome_reason_text' => 'لم تناسب الوحدات المتاحة الميزانية',
        ]);

        $this->assertSame(RequestStatus::Closed, $request->fresh()->status);
        $this->assertNull($request->fresh()->next_follow_up_at);
        $this->assertDatabaseHas('audit_events', [
            'event_key' => 'request.status_changed',
            'subject_id' => $request->id,
        ]);
    }

    public function test_successful_closed_request_creates_one_deal_and_aftercare_profile(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل صفقة',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        $requestService = app(RequestService::class);

        try {
            app(DealService::class)->createFromRequest($manager, Department::Rental, $request->id, [
                'deal_date' => now()->toDateString(),
                'agreed_total_value' => 120000,
                'currency' => 'EGP',
                'rental_term' => 'half_yearly',
                'rental_term_count' => 1,
                'payment_schedule' => 'monthly',
            ]);
            $this->fail('An active request must not become a deal.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_deals', 0);
        }

        $requestService->close($manager, Department::Rental, $request->id, ['outcome' => 'successful']);
        $deal = app(DealService::class)->createFromRequest($manager, Department::Rental, $request->id, [
            'deal_date' => now()->toDateString(),
            'agreed_total_value' => 120000,
            'currency' => 'EGP',
            'rental_term' => 'half_yearly',
            'rental_term_count' => 1,
            'payment_schedule' => 'monthly',
            'commissions' => [[
                'payer' => 'الشركة',
                'recipient' => 'الوسيط',
                'calculation_type' => 'percentage',
            ], [
                'payer' => 'الوسيط',
                'recipient' => 'الشركة',
                'calculation_type' => 'percentage',
                'agreed_value' => 120,
            ]],
        ]);

        $this->assertSame($customer->id, $deal->customer_id);
        $this->assertSame('half_yearly', $deal->rental_term);
        $this->assertSame('monthly', $deal->payment_schedule);
        $this->assertDatabaseCount('rental_deal_commissions', 2);
        $this->assertDatabaseHas('rental_deal_commissions', [
            'deal_id' => $deal->id,
            'agreed_value' => null,
        ]);
        $this->assertDatabaseHas('rental_deal_commissions', [
            'deal_id' => $deal->id,
            'calculation_type' => 'percentage',
            'agreed_value' => 120,
        ]);
        $this->assertDatabaseHas('rental_aftercare', [
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
        ]);

        app(AftercareService::class)->recordActivity($manager, Department::Rental, $customer->id, [
            'deal_id' => $deal->id,
            'channel' => 'whatsapp',
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'result' => 'تم الاطمئنان على العميل',
            'next_follow_up_at' => now()->addWeek()->toDateTimeString(),
        ]);
        $this->assertDatabaseHas('rental_aftercare', [
            'customer_id' => $customer->id,
            'next_follow_up_at' => now()->addWeek()->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('rental_aftercare_activities', 1);

        try {
            app(DealService::class)->createFromRequest($manager, Department::Rental, $request->id, [
                'deal_date' => now()->toDateString(),
                'agreed_total_value' => 120000,
                'currency' => 'EGP',
                'rental_term' => 'half_yearly',
                'rental_term_count' => 1,
                'payment_schedule' => 'monthly',
            ]);
            $this->fail('A request must not have a second deal.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('rental_deals', 1);
        }
    }

    public function test_partial_request_edit_preserves_hidden_other_descriptions_and_clears_them_when_selection_changes(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل تعديل جزئي',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $otherSource = RentalLeadSource::query()->where('is_other', true)->firstOrFail();
        $normalSource = RentalLeadSource::query()->where('is_other', false)->firstOrFail();
        $request = app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'lead_source_id' => $otherSource->id,
            'lead_source_other' => 'إحالة خاصة',
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            'unit_type' => 'other',
            'unit_type_other' => 'وحدة بحديقة داخلية',
            'finishing' => 'other',
            'finishing_other' => 'تشطيب خاص',
        ]);
        $service = app(RequestService::class);

        $service->updateDetails($manager, Department::Rental, $request->id, ['notes' => 'تعديل ملاحظة فقط']);
        $request->refresh();
        $this->assertSame('إحالة خاصة', $request->lead_source_other);
        $this->assertSame('وحدة بحديقة داخلية', $request->unit_type_other);
        $this->assertSame('تشطيب خاص', $request->finishing_other);

        $service->updateDetails($manager, Department::Rental, $request->id, [
            'unit_type' => 'apartment',
            'finishing' => 'unfinished',
            'lead_source_id' => $normalSource->id,
        ]);
        $request->refresh();
        $this->assertNull($request->unit_type_other);
        $this->assertNull($request->finishing_other);
        $this->assertNull($request->lead_source_other);
    }

    public function test_successful_resale_deal_starts_independent_aftercare(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Resale, [
            'name' => 'عميل إعادة بيع',
            'phone' => '01012345678',
            'phone_country' => 'EG',
        ]);
        $request = app(RequestService::class)->create($manager, Department::Resale, [
            'customer_id' => $customer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            'budget_declared' => 3500000,
        ]);
        app(RequestService::class)->close($manager, Department::Resale, $request->id, ['outcome' => 'successful']);

        app(DealService::class)->createFromRequest($manager, Department::Resale, $request->id, [
            'deal_date' => now()->toDateString(),
            'agreed_total_value' => 3400000,
            'currency' => 'EGP',
            'seller_name' => 'البائع',
        ]);

        $this->assertDatabaseHas('resale_aftercare', [
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
        ]);
        $this->assertDatabaseCount('resale_deals', 1);
        $this->assertDatabaseCount('rental_deals', 0);
    }
}
