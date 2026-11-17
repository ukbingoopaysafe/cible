<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'rental' => ['rental_requests', 'rental_deals', 'rental_aftercare', 'rental_aftercare_activities'],
            'resale' => ['resale_requests', 'resale_deals', 'resale_aftercare', 'resale_aftercare_activities'],
        ] as [$requestTable, $dealTable, $aftercareTable, $activityTable]) {
            $this->createMissingRequestCases($requestTable, $dealTable, $aftercareTable);
            $this->moveDealIdentifiedActivities($requestTable, $dealTable, $aftercareTable, $activityTable);
            $this->classifyLegacyProfiles($requestTable, $aftercareTable);
        }
    }

    public function down(): void
    {
        // Forward-only: request cases and their activity history are retained.
    }

    private function createMissingRequestCases(string $requestTable, string $dealTable, string $aftercareTable): void
    {
        DB::table($requestTable)
            ->where('status', 'closed')
            ->whereIn('outcome', ['successful', 'unsuccessful'])
            ->whereNotNull('customer_id')
            ->orderBy('id')
            ->chunkById(250, function ($requests) use ($dealTable, $aftercareTable): void {
                foreach ($requests as $request) {
                    $category = $request->outcome;
                    $dealId = $category === 'successful'
                        ? DB::table($dealTable)
                            ->where('request_id', $request->id)
                            ->value('id')
                        : null;
                    $case = DB::table($aftercareTable)->where('request_id', $request->id)->first();

                    if (! $case) {
                        $timestamp = $request->closed_at ?? $request->updated_at ?? now();
                        DB::table($aftercareTable)->insert([
                            'customer_id' => $request->customer_id,
                            'owner_user_id' => $request->owner_user_id,
                            'request_id' => $request->id,
                            'deal_id' => $dealId,
                            'legacy_customer_id' => null,
                            'category' => $category,
                            'next_follow_up_at' => null,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);

                        continue;
                    }

                    $updates = [];
                    if ($case->category === null) {
                        $updates['category'] = $category;
                    }
                    if ($case->deal_id === null && $dealId !== null) {
                        $updates['deal_id'] = $dealId;
                    }
                    if ($updates !== []) {
                        $updates['updated_at'] = now();
                        DB::table($aftercareTable)->where('id', $case->id)->update($updates);
                    }
                }
            });
    }

    private function moveDealIdentifiedActivities(
        string $requestTable,
        string $dealTable,
        string $aftercareTable,
        string $activityTable,
    ): void {
        DB::table($activityTable)
            ->whereNotNull('deal_id')
            ->orderBy('id')
            ->chunkById(250, function ($activities) use ($requestTable, $dealTable, $aftercareTable, $activityTable): void {
                foreach ($activities as $activity) {
                    $source = DB::table($aftercareTable)->where('id', $activity->aftercare_id)->first();
                    if (! $source) {
                        continue;
                    }

                    $identifiedRequest = DB::table($dealTable)
                        ->join($requestTable, "{$requestTable}.id", '=', "{$dealTable}.request_id")
                        ->where("{$dealTable}.id", $activity->deal_id)
                        ->where("{$requestTable}.customer_id", $source->customer_id)
                        ->value("{$dealTable}.request_id");
                    if (! $identifiedRequest) {
                        continue;
                    }

                    $target = DB::table($aftercareTable)
                        ->where('request_id', $identifiedRequest)
                        ->where('customer_id', $source->customer_id)
                        ->first();
                    if ($target && (int) $target->id !== (int) $activity->aftercare_id) {
                        DB::table($activityTable)->where('id', $activity->id)->update([
                            'aftercare_id' => $target->id,
                            'updated_at' => now(),
                        ]);
                    }
                }
            });
    }

    private function classifyLegacyProfiles(string $requestTable, string $aftercareTable): void
    {
        DB::table($aftercareTable)
            ->whereNull('request_id')
            ->whereNull('legacy_customer_id')
            ->whereNull('category')
            ->orderBy('id')
            ->chunkById(250, function ($profiles) use ($requestTable, $aftercareTable): void {
                foreach ($profiles as $profile) {
                    $customerHasRequest = DB::table($requestTable)->where('customer_id', $profile->customer_id)->exists();
                    $legacyCaseExists = DB::table($aftercareTable)
                        ->where('legacy_customer_id', $profile->customer_id)
                        ->exists();

                    if ($customerHasRequest || $legacyCaseExists) {
                        continue;
                    }

                    DB::table($aftercareTable)->where('id', $profile->id)->update([
                        'legacy_customer_id' => $profile->customer_id,
                        'category' => 'legacy_without_request',
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
