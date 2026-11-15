<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KNOWN_TYPES = [
        'إحالة من موظف' => 'person_referral',
        'إحالة من عميل أو معرفة' => 'person_referral',
        'وسائل التواصل الاجتماعي' => 'awareness',
        'إعلان إلكتروني' => 'awareness',
        'الموقع الإلكتروني' => 'awareness',
        'مكالمة هاتفية' => 'legacy_contact_method',
        'زيارة مباشرة' => 'legacy_contact_method',
        'واتساب' => 'legacy_contact_method',
    ];

    public function up(): void
    {
        $connection = $this->getConnection();
        $schema = Schema::connection($connection);
        $database = DB::connection($connection);

        foreach (['rental', 'resale'] as $department) {
            $sourceTable = "{$department}_lead_sources";
            $requestTable = "{$department}_requests";

            $schema->table($sourceTable, function (Blueprint $table): void {
                $table->string('type', 32)->nullable();
            });
            $schema->table($requestTable, function (Blueprint $table): void {
                $table->string('lead_source_referrer_name', 255)->nullable();
                $table->boolean('lead_source_referrer_unknown')->nullable();
            });

            $database->table($sourceTable)
                ->where('is_other', true)
                ->update(['type' => 'other', 'is_active' => true, 'updated_at' => now()]);

            foreach (self::KNOWN_TYPES as $name => $type) {
                $source = $database->table($sourceTable)->where('name', $name);
                if ($type === 'legacy_contact_method') {
                    $source->update(['type' => $type, 'is_active' => false, 'updated_at' => now()]);
                } else {
                    $source->update(['type' => $type, 'updated_at' => now()]);
                }
            }

            $managementSource = $database->table($sourceTable)->where('name', 'الإدارة العليا');
            if ($managementSource->exists()) {
                $managementSource->update([
                    'type' => 'person_referral',
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                $database->table($sourceTable)->insert([
                    'name' => 'الإدارة العليا',
                    'type' => 'person_referral',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $database->table($sourceTable)
                ->whereNull('type')
                ->update(['type' => 'unclassified_legacy', 'is_active' => false, 'updated_at' => now()]);

            if ($database->table($sourceTable)->whereNull('type')->exists()) {
                throw new RuntimeException("Lead source type backfill left null values in {$sourceTable}.");
            }

            $schema->table($sourceTable, function (Blueprint $table): void {
                $table->string('type', 32)->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Refusing to remove lead source classifications or referral snapshots; use a forward migration.');
    }
};
