<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        $nullValues = [];

        foreach (['rental', 'resale'] as $section) {
            $table = "{$section}_deal_commissions";
            $count = DB::table($table)
                ->whereNull('agreed_value')
                ->count();

            if ($count > 0) {
                $nullValues[$table] = $count;
            }
        }

        if ($nullValues !== []) {
            $details = collect($nullValues)
                ->map(fn (int $count, string $table): string => "{$table}: {$count}")
                ->implode(', ');

            throw new RuntimeException(
                "Cannot roll back nullable commission values while NULL agreed_value records exist ({$details}). "
                .'Keep this migration applied until the values are resolved explicitly.'
            );
        }

        foreach (['rental', 'resale'] as $section) {
            Schema::table("{$section}_deal_commissions", function (Blueprint $table): void {
                $table->decimal('agreed_value', 15, 2)->nullable(false)->change();
            });
        }
    }
};
