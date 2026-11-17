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
                $table->string('stage', 24)->default('unit_search')->change();
            });

            DB::table($tableName)
                ->whereIn('stage', ['intake', 'qualification'])
                ->update(['stage' => 'unit_search']);
        }
    }

    public function down(): void
    {
        // Forward-only: intake and qualification now share the unit_search value.
    }
};
