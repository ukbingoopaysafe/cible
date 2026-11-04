<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONTACTS = [
        'rental_customers' => ['type' => 'rental_customer', 'required' => true],
        'resale_customers' => ['type' => 'resale_customer', 'required' => true],
        'rental_brokers' => ['type' => 'rental_broker', 'required' => false],
        'resale_brokers' => ['type' => 'resale_broker', 'required' => false],
    ];

    public function up(): void
    {
        foreach (array_keys(self::CONTACTS) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropUnique("{$tableName}_phone_e164_unique");
                $table->dropColumn(['phone_e164', 'phone_country']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::CONTACTS as $tableName => $contact) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('phone_e164', 16)->nullable()->after('name');
                $table->char('phone_country', 2)->nullable()->after('phone_e164');
            });

            DB::table('contact_phone_numbers')
                ->where('phoneable_type', $contact['type'])
                ->where('is_primary', true)
                ->select(['phoneable_id', 'phone_e164', 'phone_country'])
                ->orderBy('phoneable_id')
                ->chunk(500, function ($phones) use ($tableName): void {
                    foreach ($phones as $phone) {
                        DB::table($tableName)
                            ->where('id', $phone->phoneable_id)
                            ->update([
                                'phone_e164' => $phone->phone_e164,
                                'phone_country' => $phone->phone_country,
                            ]);
                    }
                });

            if ($contact['required'] && DB::table($tableName)
                ->where(fn ($query) => $query->whereNull('phone_e164')->orWhereNull('phone_country'))
                ->exists()) {
                throw new RuntimeException("Cannot restore required legacy phone numbers for {$tableName}: at least one customer has no primary phone.");
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName, $contact): void {
                if ($contact['required']) {
                    $table->string('phone_e164', 16)->nullable(false)->change();
                    $table->char('phone_country', 2)->nullable(false)->change();
                }

                $table->unique('phone_e164', "{$tableName}_phone_e164_unique");
            });
        }
    }
};
