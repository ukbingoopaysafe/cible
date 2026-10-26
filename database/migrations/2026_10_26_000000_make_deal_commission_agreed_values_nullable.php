<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::table("{$section}_deal_commissions", function (Blueprint $table): void {
                $table->decimal('agreed_value', 15, 2)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::table("{$section}_deal_commissions", function (Blueprint $table): void {
                $table->decimal('agreed_value', 15, 2)->nullable(false)->change();
            });
        }
    }
};
