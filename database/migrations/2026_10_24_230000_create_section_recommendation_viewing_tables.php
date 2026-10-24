<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $section) {
            $requestTable = "{$section}_requests";
            $recommendationTable = "{$section}_recommendations";

            Schema::create($recommendationTable, function (Blueprint $table) use ($requestTable): void {
                $table->id();
                $table->foreignId('request_id')->constrained($requestTable)->restrictOnDelete();
                $table->string('unit_code')->nullable();
                $table->string('listing_code')->nullable();
                $table->string('unit_type', 80)->nullable();
                $table->decimal('unit_area', 10, 2)->nullable();
                $table->text('location_description')->nullable();
                $table->decimal('asking_price', 15, 2)->nullable();
                $table->char('currency', 3)->default('EGP');
                $table->string('status', 20)->default('proposed');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['request_id', 'status']);
            });

            Schema::create("{$section}_field_inspections", function (Blueprint $table) use ($recommendationTable): void {
                $table->id();
                $table->foreignId('recommendation_id')->constrained($recommendationTable)->restrictOnDelete();
                $table->string('field_representative_name', 160);
                $table->dateTime('inspected_at');
                $table->string('photo_match', 24);
                $table->text('observed_defects')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['recommendation_id', 'inspected_at']);
            });

            Schema::create("{$section}_viewings", function (Blueprint $table) use ($requestTable, $recommendationTable): void {
                $table->id();
                $table->foreignId('request_id')->constrained($requestTable)->restrictOnDelete();
                $table->foreignId('recommendation_id')->nullable()->constrained($recommendationTable)->restrictOnDelete();
                $table->dateTime('scheduled_at');
                $table->string('field_representative_name', 160)->nullable();
                $table->string('result', 24)->nullable();
                $table->text('customer_feedback')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
                $table->index(['request_id', 'scheduled_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::dropIfExists("{$section}_viewings");
            Schema::dropIfExists("{$section}_field_inspections");
            Schema::dropIfExists("{$section}_recommendations");
        }
    }
};
