<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resale_requests', function (Blueprint $table): void {
            $table->string('unit_type_other')->nullable()->after('unit_type');
        });
    }

    public function down(): void
    {
        Schema::table('resale_requests', function (Blueprint $table): void {
            $table->dropColumn('unit_type_other');
        });
    }
};
