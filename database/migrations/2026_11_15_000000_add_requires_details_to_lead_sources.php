<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $department) {
            $table = "{$department}_lead_sources";

            Schema::table($table, function (Blueprint $table): void {
                $table->boolean('requires_details')->default(false);
            });

            DB::table($table)
                ->where('name', 'وسائل التواصل الاجتماعي')
                ->update(['requires_details' => true]);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Refusing to remove source detail requirements; keep the schema and use a forward migration.');
    }
};
