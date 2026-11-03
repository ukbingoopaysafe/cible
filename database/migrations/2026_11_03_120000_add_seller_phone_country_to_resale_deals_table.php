<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resale_deals', function (Blueprint $table): void {
            $table->char('seller_phone_country', 2)->nullable()->after('seller_phone');
        });
    }

    public function down(): void
    {
        Schema::table('resale_deals', function (Blueprint $table): void {
            $table->dropColumn('seller_phone_country');
        });
    }
};
