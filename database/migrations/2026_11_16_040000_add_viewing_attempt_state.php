<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental_viewings', 'resale_viewings'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dateTime('scheduled_at')->nullable()->change();
                $table->string('appointment_status', 24)->default('scheduled')->after('scheduled_at');
                $table->foreignId('rescheduled_from_id')->nullable()->after('appointment_status')
                    ->constrained($tableName)->restrictOnDelete();
                $table->index(['appointment_status', 'scheduled_at']);
            });

            DB::table($tableName)
                ->where('result', 'cancelled')
                ->update(['appointment_status' => 'cancelled', 'result' => null]);

            DB::table($tableName)
                ->whereNotNull('result')
                ->update(['appointment_status' => 'completed']);
        }
    }

    public function down(): void
    {
        // Forward-only: keep appointment history and the rescheduling chain.
    }
};
