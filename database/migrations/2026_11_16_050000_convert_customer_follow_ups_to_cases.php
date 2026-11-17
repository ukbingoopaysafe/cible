<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'rental' => ['rental_requests', 'rental_deals', 'rental_customers'],
            'resale' => ['resale_requests', 'resale_deals', 'resale_customers'],
        ] as $section => [$requestTable, $dealTable, $customerTable]) {
            $tableName = "{$section}_aftercare";

            Schema::table($tableName, function (Blueprint $table) use ($requestTable, $dealTable, $customerTable): void {
                // InnoDB may use the unique customer index to satisfy the FK.
                // Install its replacement before dropping the unique constraint.
                $table->index('customer_id');
                $table->dropUnique(['customer_id']);
                $table->foreignId('request_id')->nullable()->unique()->constrained($requestTable)->restrictOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained($dealTable)->restrictOnDelete();
                $table->foreignId('legacy_customer_id')->nullable()->unique()->constrained($customerTable)->restrictOnDelete();
                $table->string('category', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Forward-only: request cases and their activity history are retained.
    }
};
