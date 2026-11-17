<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental_requests', 'resale_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->text('pause_reason')->nullable();
                $table->dateTime('resume_at')->nullable()->index();
                $table->dateTime('resume_follow_up_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Forward-only: preserve pause reasons and planned resumption history.
    }
};
