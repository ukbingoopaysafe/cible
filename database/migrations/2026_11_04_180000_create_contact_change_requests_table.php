<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('department', 16);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->json('before_values');
            $table->json('after_values');
            $table->boolean('reviewed_similar_names')->default(false);
            $table->string('status', 16)->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'status'], 'contact_change_subject_status_index');
            $table->index(['department', 'status', 'created_at'], 'contact_change_department_status_index');
            $table->index(['requested_by', 'created_at'], 'contact_change_requester_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_change_requests');
    }
};
