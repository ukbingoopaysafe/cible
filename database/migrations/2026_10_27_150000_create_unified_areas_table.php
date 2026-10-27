<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table): void {
                $table->id();
                $table->string('governorate', 40);
                $table->string('name', 100);
                $table->softDeletes();
                $table->timestamps();
                $table->unique(['governorate', 'name']);
            });
        }

        if (Schema::hasTable('rental_areas')) {
            $existingRentalAreas = DB::table('rental_areas')->get();
            foreach ($existingRentalAreas as $area) {
                DB::table('areas')->updateOrInsert(
                    ['governorate' => $area->governorate, 'name' => $area->name],
                    [
                        'created_at' => $area->created_at ?? now(),
                        'updated_at' => $area->updated_at ?? now(),
                        'deleted_at' => $area->deleted_at ?? null,
                    ]
                );
            }
        }

        if (Schema::hasTable('resale_areas')) {
            $existingResaleAreas = DB::table('resale_areas')->get();
            foreach ($existingResaleAreas as $area) {
                DB::table('areas')->updateOrInsert(
                    ['governorate' => $area->governorate, 'name' => $area->name],
                    [
                        'created_at' => $area->created_at ?? now(),
                        'updated_at' => $area->updated_at ?? now(),
                        'deleted_at' => $area->deleted_at ?? null,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
