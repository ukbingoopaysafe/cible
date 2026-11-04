<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Propaganistas\LaravelPhone\PhoneNumber;

return new class extends Migration
{
    private const CONTACT_TYPES = [
        'rental_customers' => 'rental_customer',
        'resale_customers' => 'resale_customer',
        'rental_brokers' => 'rental_broker',
        'resale_brokers' => 'resale_broker',
    ];

    public function up(): void
    {
        Schema::create('contact_phone_numbers', function (Blueprint $table): void {
            $table->id();
            $table->morphs('phoneable');
            $table->string('phone_e164', 16);
            $table->char('phone_country', 2);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['phoneable_type', 'phone_e164'], 'contact_phones_type_number_unique');
            $table->index(['phoneable_type', 'phoneable_id', 'is_primary', 'sort_order'], 'contact_phones_owner_order_index');
        });

        foreach (self::CONTACT_TYPES as $tableName => $type) {
            $expectedCount = DB::table($tableName)->whereNotNull('phone_e164')->count();

            DB::table($tableName)
                ->whereNotNull('phone_e164')
                ->orderBy('id')
                ->chunkById(500, function ($contacts) use ($type, $tableName): void {
                    $now = now();
                    $rows = $contacts->map(function (object $contact) use ($type, $tableName, $now): array {
                        $country = $contact->phone_country;

                        if (blank($country)) {
                            try {
                                $country = (new PhoneNumber($contact->phone_e164))->getCountry();
                            } catch (Throwable $exception) {
                                throw new RuntimeException("Cannot infer country for {$tableName} record {$contact->id}.", previous: $exception);
                            }
                        }

                        if (! is_string($country) || strlen($country) !== 2) {
                            throw new RuntimeException("Cannot infer a valid phone country for {$tableName} record {$contact->id}.");
                        }

                        return [
                            'phoneable_type' => $type,
                            'phoneable_id' => $contact->id,
                            'phone_e164' => $contact->phone_e164,
                            'phone_country' => $country,
                            'is_primary' => true,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    })->all();

                    DB::table('contact_phone_numbers')->insert($rows);
                });

            $migratedCount = DB::table('contact_phone_numbers')->where('phoneable_type', $type)->count();
            if ($migratedCount !== $expectedCount) {
                throw new RuntimeException("Phone backfill count mismatch for {$tableName}: expected {$expectedCount}, got {$migratedCount}.");
            }

            if (str_ends_with($tableName, '_customers')) {
                $customersWithoutPrimary = DB::table("{$tableName} as contacts")
                    ->whereNotExists(fn ($query) => $query
                        ->selectRaw('1')
                        ->from('contact_phone_numbers as numbers')
                        ->where('numbers.phoneable_type', $type)
                        ->whereColumn('numbers.phoneable_id', 'contacts.id')
                        ->where('numbers.is_primary', true))
                    ->count();

                if ($customersWithoutPrimary > 0) {
                    throw new RuntimeException("Phone backfill left {$customersWithoutPrimary} customers without a primary number in {$tableName}.");
                }
            }
        }
    }

    public function down(): void
    {
        $hasMultipleNumbers = DB::table('contact_phone_numbers')
            ->select('phoneable_type', 'phoneable_id')
            ->groupBy('phoneable_type', 'phoneable_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleNumbers) {
            throw new RuntimeException('Cannot roll back contact phone numbers while additional numbers exist. Remove or export them before retrying.');
        }

        Schema::dropIfExists('contact_phone_numbers');
    }
};
