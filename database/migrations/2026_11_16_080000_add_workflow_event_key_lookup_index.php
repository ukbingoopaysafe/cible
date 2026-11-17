<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index('workflow_event_key', 'notifications_workflow_event_key_index');
        });
    }

    public function down(): void
    {
        // Forward-only: notification history and its lookup index are retained.
    }
};
