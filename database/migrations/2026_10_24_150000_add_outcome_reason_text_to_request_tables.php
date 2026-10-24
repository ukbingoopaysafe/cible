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
                $table->text('outcome_reason_text')->nullable()->after('outcome');
            });
        }
    }

    public function down(): void
    {
        foreach (['resale_requests', 'rental_requests'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('outcome_reason_text');
            });
        }
    }
};
