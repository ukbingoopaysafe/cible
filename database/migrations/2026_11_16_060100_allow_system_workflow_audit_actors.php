<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->foreignId('actor_user_id')->nullable()->change();
            $table->foreign('actor_user_id', 'audit_events_actor_user_id_foreign')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        foreach (['rental_request_status_histories', 'resale_request_status_histories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['changed_by']);
                $table->foreignId('changed_by')->nullable()->change();
                $table->foreign('changed_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Forward-only: null actors identify system-generated workflow history.
    }
};
