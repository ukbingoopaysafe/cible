<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->string('workflow_event_key', 191)->nullable();
            $table->unique(
                ['notifiable_type', 'notifiable_id', 'workflow_event_key'],
                'notifications_workflow_event_unique',
            );
        });
    }

    public function down(): void
    {
        // Forward-only: notification and read-state history is retained.
    }
};
