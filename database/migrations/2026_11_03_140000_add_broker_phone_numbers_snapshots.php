<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental_requests', 'resale_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->json('broker_phone_numbers_snapshot')->nullable()->after('broker_phone_snapshot');
            });
        }
    }

    public function down(): void
    {
        foreach (['rental_requests', 'resale_requests'] as $tableName) {
            DB::table($tableName)
                ->whereNotNull('broker_phone_numbers_snapshot')
                ->select(['id', 'broker_phone_numbers_snapshot'])
                ->orderBy('id')
                ->chunk(500, function ($requests) use ($tableName): void {
                    foreach ($requests as $request) {
                        $phones = json_decode($request->broker_phone_numbers_snapshot, true, 512, JSON_THROW_ON_ERROR);

                        if (! is_array($phones) || ! array_is_list($phones) || count($phones) > 1) {
                            throw new RuntimeException("Cannot remove broker phone history from {$tableName}: request {$request->id} contains multiple phone numbers.");
                        }
                    }
                });

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('broker_phone_numbers_snapshot');
            });
        }
    }
};
