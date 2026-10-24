<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_request_id')->constrained('rental_requests')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_party', 16);
            $table->string('contact_name_snapshot');
            $table->string('channel', 24);
            $table->dateTime('occurred_at');
            $table->text('result');
            $table->text('notes')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamps();
            $table->index(['rental_request_id', 'occurred_at']);
        });

        Schema::create('resale_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resale_request_id')->constrained('resale_requests')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_party', 16);
            $table->string('contact_name_snapshot');
            $table->string('channel', 24);
            $table->dateTime('occurred_at');
            $table->text('result');
            $table->text('notes')->nullable();
            $table->text('next_action')->nullable();
            $table->timestamps();
            $table->index(['resale_request_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resale_activities');
        Schema::dropIfExists('rental_activities');
    }
};
