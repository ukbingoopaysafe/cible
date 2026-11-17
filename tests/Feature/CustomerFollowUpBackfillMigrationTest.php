<?php

namespace Tests\Feature;

use App\Enums\Department;
use App\Models\RentalArea;
use App\Models\ResaleArea;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerFollowUpBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_creates_outcome_cases_and_preserves_unprovable_history_idempotently(): void
    {
        $manager = User::factory()->create(['is_company_manager' => true]);
        $customerService = app(CustomerService::class);
        $customer = $customerService->create($manager, Department::Rental, [
            'name' => 'عميل ترحيل حالات المتابعة',
            'phone' => '01012345801',
            'phone_country' => 'EG',
        ]);
        $requests = [];
        foreach (['successful', 'unsuccessful'] as $outcome) {
            $requests[$outcome] = app(RequestService::class)->create($manager, Department::Rental, [
                'customer_id' => $customer->id,
                'area_id' => RentalArea::query()->firstOrFail()->id,
                'next_follow_up_at' => now()->addDay()->toDateTimeString(),
            ]);
            DB::table('rental_requests')->where('id', $requests[$outcome]->id)->update([
                'status' => 'closed',
                'outcome' => $outcome,
                'closed_at' => now()->subDays($outcome === 'successful' ? 2 : 1),
            ]);
        }

        $successfulDealId = (int) DB::table('rental_deals')->insertGetId([
            'request_id' => $requests['successful']->id,
            'customer_id' => $customer->id,
            'deal_date' => now()->subDays(2)->toDateString(),
            'agreed_total_value' => 20000,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $historicalProfileId = (int) DB::table('rental_aftercare')->insertGetId([
            'customer_id' => $customer->id,
            'owner_user_id' => $manager->id,
            'next_follow_up_at' => now()->addMonth(),
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);
        $matchedActivityId = $this->insertActivity($historicalProfileId, $manager->id, $successfulDealId, 'نشاط مرتبط بصفقة معروفة');
        $unmatchedActivityId = $this->insertActivity($historicalProfileId, $manager->id, null, 'نشاط لا يمكن ربطه بطلب');

        $legacyCustomer = $customerService->create($manager, Department::Rental, [
            'name' => 'عميل قديم بلا طلب للترحيل',
            'phone' => '01012345802',
            'phone_country' => 'EG',
        ]);
        $legacyProfileId = (int) DB::table('rental_aftercare')->insertGetId([
            'customer_id' => $legacyCustomer->id,
            'owner_user_id' => $manager->id,
            'next_follow_up_at' => now()->addDays(3),
            'created_at' => now()->subMonths(2),
            'updated_at' => now()->subMonths(2),
        ]);
        $legacyActivityId = $this->insertActivity($legacyProfileId, $manager->id, null, 'تاريخ عميل بلا طلب');

        $saleCustomer = $customerService->create($manager, Department::Resale, [
            'name' => 'عميل بيع لترحيل الحالات',
            'phone' => '01012345803',
            'phone_country' => 'EG',
        ]);
        $saleRequest = app(RequestService::class)->create($manager, Department::Resale, [
            'customer_id' => $saleCustomer->id,
            'area_id' => ResaleArea::query()->firstOrFail()->id,
            'next_follow_up_at' => now()->addDay()->toDateTimeString(),
        ]);
        DB::table('resale_requests')->where('id', $saleRequest->id)->update([
            'status' => 'closed',
            'outcome' => 'successful',
            'closed_at' => now()->subDays(3),
        ]);
        $saleDealId = (int) DB::table('resale_deals')->insertGetId([
            'request_id' => $saleRequest->id,
            'customer_id' => $saleCustomer->id,
            'deal_date' => now()->subDays(3)->toDateString(),
            'agreed_total_value' => 2000000,
            'currency' => 'EGP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migrationPath = database_path('migrations/2026_11_16_090000_backfill_customer_follow_up_cases.php');
        $this->assertFileExists($migrationPath);
        $migration = require $migrationPath;
        $migration->up();

        $successfulCase = DB::table('rental_aftercare')->where('request_id', $requests['successful']->id)->first();
        $unsuccessfulCase = DB::table('rental_aftercare')->where('request_id', $requests['unsuccessful']->id)->first();
        $this->assertNotNull($successfulCase);
        $this->assertNotNull($unsuccessfulCase);
        $this->assertSame('successful', $successfulCase->category);
        $this->assertSame($successfulDealId, (int) $successfulCase->deal_id);
        $this->assertSame('unsuccessful', $unsuccessfulCase->category);
        $this->assertSame($manager->id, (int) $successfulCase->owner_user_id);
        $this->assertSame((int) $successfulCase->id, (int) DB::table('rental_aftercare_activities')->where('id', $matchedActivityId)->value('aftercare_id'));

        $saleCase = DB::table('resale_aftercare')->where('request_id', $saleRequest->id)->first();
        $this->assertNotNull($saleCase);
        $this->assertSame('successful', $saleCase->category);
        $this->assertSame($saleDealId, (int) $saleCase->deal_id);

        $historyProfile = DB::table('rental_aftercare')->where('id', $historicalProfileId)->first();
        $this->assertNull($historyProfile->request_id);
        $this->assertNull($historyProfile->category);
        $this->assertNull($historyProfile->legacy_customer_id);
        $this->assertSame($historicalProfileId, (int) DB::table('rental_aftercare_activities')->where('id', $unmatchedActivityId)->value('aftercare_id'));

        $legacyProfile = DB::table('rental_aftercare')->where('id', $legacyProfileId)->first();
        $this->assertSame($legacyCustomer->id, (int) $legacyProfile->legacy_customer_id);
        $this->assertSame('legacy_without_request', $legacyProfile->category);
        $this->assertSame($legacyProfileId, (int) DB::table('rental_aftercare_activities')->where('id', $legacyActivityId)->value('aftercare_id'));

        $migration->up();

        $this->assertSame(2, DB::table('rental_aftercare')->whereIn('request_id', [$requests['successful']->id, $requests['unsuccessful']->id])->count());
        $this->assertSame(3, DB::table('rental_aftercare_activities')->whereIn('id', [$matchedActivityId, $unmatchedActivityId, $legacyActivityId])->count());
    }

    private function insertActivity(int $profileId, int $actorId, ?int $dealId, string $result): int
    {
        return (int) DB::table('rental_aftercare_activities')->insertGetId([
            'aftercare_id' => $profileId,
            'deal_id' => $dealId,
            'actor_user_id' => $actorId,
            'channel' => 'phone',
            'occurred_at' => now()->subDay(),
            'result' => $result,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
