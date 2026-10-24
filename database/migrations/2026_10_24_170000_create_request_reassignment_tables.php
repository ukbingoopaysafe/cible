<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'rental_request_reassignments' => 'rental_requests',
            'resale_request_reassignments' => 'resale_requests',
        ] as $tableName => $requestTable) {
            Schema::create($tableName, function (Blueprint $table) use ($requestTable): void {
                $table->id();
                $table->foreignId('request_id')->constrained($requestTable)->restrictOnDelete();
                $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
                $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('to_user_id')->constrained('users')->restrictOnDelete();
                $table->text('reason');
                $table->string('status', 16)->default('pending');
                $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->index(['request_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_request_reassignments');
        Schema::dropIfExists('rental_request_reassignments');
    }
};
