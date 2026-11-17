<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_requests', function (Blueprint $table): void {
            $table->string('client_category', 24)->nullable();
        });

        Schema::table('resale_requests', function (Blueprint $table): void {
            $table->string('client_category', 24)->nullable();
            $table->unsignedSmallInteger('occupants')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->unsignedSmallInteger('building_year')->nullable();
            $table->boolean('elevator_required')->nullable();
            $table->boolean('water_meter')->nullable();
            $table->string('electricity_meter_type', 16)->nullable();
            $table->boolean('gas_available')->nullable();
        });
    }

    public function down(): void
    {
        // Forward-only: keep request requirements and any data entered into them.
    }
};
