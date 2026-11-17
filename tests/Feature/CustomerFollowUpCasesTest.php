<?php

namespace Tests\Feature;

use App\Enums\CustomerFollowUpCategory;
use App\Enums\Department;
use App\Filament\Resources\RentalCustomerFollowUps\Pages\ListRentalCustomerFollowUps;
use App\Models\RentalArea;
use App\Models\RentalCustomer;
use App\Models\RentalCustomerFollowUp;
use App\Models\RentalRequest;
use App\Models\RentalRequestCloseReason;
use App\Models\User;
use App\Services\CustomerFollowUpService;
use App\Services\CustomerService;
use App\Services\RequestService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerFollowUpCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_closed_request_creates_its_own_outcome_case_for_the_customer(): void
    {
        [$manager, $customer, $requests] = $this->customerWithTwoRequests();
        $service = app(RequestService::class);
        $service->close($manager, Department::Rental, $requests[0]->id, ['outcome' => 'successful']);
        $reason = RentalRequestCloseReason::query()->where('is_active', true)->firstOrFail();
        $service->close($manager, Department::Rental, $requests[1]->id, [
            'outcome' => 'unsuccessful',
            'outcome_reason_id' => $reason->id,
        ]);

        $cases = RentalCustomerFollowUp::query()->where('customer_id', $customer->id)->orderBy('request_id')->get();
        $this->assertCount(2, $cases);
        $this->assertSame($requests[0]->id, $cases[0]->request_id);
        $this->assertSame(CustomerFollowUpCategory::Successful, $cases[0]->category);
        $this->assertSame($requests[1]->id, $cases[1]->request_id);
        $this->assertSame(CustomerFollowUpCategory::Unsuccessful, $cases[1]->category);

        $followUpService = app(CustomerFollowUpService::class);
        $activity = $followUpService->recordActivity($manager, Department::Rental, $customer->id, $cases[1]->id, [
            'channel' => 'phone',
            'occurred_at' => now()->subMinute()->toDateTimeString(),
            'result' => 'تم التواصل بعد إغلاق الطلب',
            'next_follow_up_at' => now()->addWeek()->toDateTimeString(),
        ]);
        $this->assertSame($cases[1]->id, $activity->aftercare_id);
        $this->assertNull($cases[0]->fresh()->next_follow_up_at);
        $this->assertNotNull($cases[1]->fresh()->next_follow_up_at);
    }

    public function test_legacy_follow_up_is_only_allowed_when_customer_has_no_request(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل قديم بلا طلب', 'phone' => '01012345710', 'phone_country' => 'EG',
        ]);
        $service = app(CustomerFollowUpService::class);
        $legacy = $service->startForCustomer($manager, Department::Rental, $customer->id);

        $this->assertSame(CustomerFollowUpCategory::LegacyWithoutRequest, $legacy->category);
        $this->assertSame($customer->id, $legacy->legacy_customer_id);

        app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        try {
            $service->startForCustomer($manager, Department::Rental, $customer->id);
            $this->fail('A customer with a request cannot receive a no-request case.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer', $exception->errors());
        }
    }

    public function test_history_only_profile_cannot_be_changed_or_receive_new_activity(): void
    {
        [$manager, $customer] = $this->customerWithTwoRequests();
        $manager->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $newOwner = User::factory()->create(['is_company_manager' => true]);
        $historyOnly = RentalCustomerFollowUp::query()->create([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'next_follow_up_at' => now()->addWeek(),
        ]);
        $originalDueDate = $historyOnly->next_follow_up_at->toDateTimeString();

        try {
            app(CustomerFollowUpService::class)->recordActivity($manager, Department::Rental, $customer->id, $historyOnly->id, [
                'channel' => 'phone',
                'occurred_at' => now()->subMinute()->toDateTimeString(),
                'result' => 'يجب ألا يُضاف إلى سجل تاريخي فقط',
                'next_follow_up_at' => now()->addDays(2)->toDateTimeString(),
            ]);
            $this->fail('History-only profiles must not accept new activity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('follow_up', $exception->errors());
        }

        try {
            app(CustomerFollowUpService::class)->reassignOwner($manager, Department::Rental, $historyOnly->id, $newOwner->id);
            $this->fail('History-only profiles must not be reassigned.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('follow_up', $exception->errors());
        }

        $this->assertSame(0, DB::table('rental_aftercare_activities')->where('aftercare_id', $historyOnly->id)->count());
        $this->assertSame($manager->id, $historyOnly->fresh()->owner_user_id);
        $this->assertSame($originalDueDate, $historyOnly->fresh()->next_follow_up_at->toDateTimeString());

        Livewire::actingAs($manager)
            ->test(ListRentalCustomerFollowUps::class)
            ->assertCanSeeTableRecords([$historyOnly])
            ->assertActionHidden(TestAction::make('recordCustomerFollowUpActivity')->table($historyOnly))
            ->assertActionHidden(TestAction::make('reassignOwner')->table($historyOnly));
    }

    public function test_legacy_case_becomes_read_only_after_customer_gets_a_request(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل قديم أصبح له طلب',
            'phone' => '01012345804',
            'phone_country' => 'EG',
        ]);
        $service = app(CustomerFollowUpService::class);
        $legacy = $service->startForCustomer($manager, Department::Rental, $customer->id);
        app(RequestService::class)->create($manager, Department::Rental, [
            'customer_id' => $customer->id,
            'area_id' => RentalArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);

        try {
            $service->recordActivity($manager, Department::Rental, $customer->id, $legacy->id, [
                'channel' => 'phone',
                'occurred_at' => now()->subMinute()->toDateTimeString(),
                'result' => 'لا يجب إضافة نشاط إلى حالة legacy بعد وجود طلب',
            ]);
            $this->fail('A legacy case must become read-only once its customer has a request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('follow_up', $exception->errors());
        }

        $this->assertSame(0, DB::table('rental_aftercare_activities')->where('aftercare_id', $legacy->id)->count());
        $this->view('filament.modals.customer-follow-up-history', [
            'customerFollowUps' => collect([$legacy->fresh()->load(['customer', 'request', 'deal', 'owner', 'activities.actor', 'activities.deal'])]),
        ])
            ->assertSee('سجل تاريخي للقراءة فقط')
            ->assertDontSee('عميل قديم بدون طلب مسجل');
    }

    /** @return array{User, RentalCustomer, list<RentalRequest>} */
    private function customerWithTwoRequests(): array
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customer = app(CustomerService::class)->create($manager, Department::Rental, [
            'name' => 'عميل له أكثر من طلب', 'phone' => '01012345711', 'phone_country' => 'EG',
        ]);
        $requests = [];
        for ($i = 0; $i < 2; $i++) {
            $requests[] = app(RequestService::class)->create($manager, Department::Rental, [
                'customer_id' => $customer->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'next_follow_up_at' => now()->addDays($i + 1)->toDateTimeString(),
            ]);
        }

        return [$manager, $customer, $requests];
    }
}
