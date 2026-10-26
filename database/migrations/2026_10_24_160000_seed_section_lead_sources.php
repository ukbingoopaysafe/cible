<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sources = [
            'مكالمة هاتفية',
            'زيارة مباشرة',
            'إحالة من موظف',
            'إحالة من عميل أو معرفة',
            'وسائل التواصل الاجتماعي',
            'إعلان إلكتروني',
            'الموقع الإلكتروني',
            'واتساب',
        ];

        foreach (['rental_lead_sources', 'resale_lead_sources'] as $tableName) {
            $now = now();
            DB::table($tableName)->insertOrIgnore(array_map(
                static fn (string $name): array => [
                    'name' => $name,
                    'is_other' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $sources,
            ));
        }
    }

    public function down(): void
    {
        // Reference values may already be attached to requests, so rollback preserves them.
    }
};
