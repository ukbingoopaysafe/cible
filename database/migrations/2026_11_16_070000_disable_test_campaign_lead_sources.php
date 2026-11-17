<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'rental_lead_sources' => 'حملة اختبار الإيجار',
            'resale_lead_sources' => 'حملة اختبار إعادة البيع',
        ] as $tableName => $name) {
            DB::table($tableName)
                ->where('name', $name)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Keep catalog rows inactive; their historical request references remain valid.
    }
};
