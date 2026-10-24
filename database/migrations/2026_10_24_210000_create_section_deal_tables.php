<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental' => 'rental_requests', 'resale' => 'resale_requests'] as $section => $requestTable) {
            $customerTable = $section === 'rental' ? 'rental_customers' : 'resale_customers';

            Schema::create("{$section}_deals", function (Blueprint $table) use ($requestTable, $customerTable, $section): void {
                $table->id();
                $table->foreignId('request_id')->unique()->constrained($requestTable)->restrictOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained($customerTable)->restrictOnDelete();
                $table->foreignId('broker_id')->nullable()->constrained("{$section}_brokers")->restrictOnDelete();
                $table->date('deal_date');
                $table->decimal('agreed_total_value', 15, 2);
                $table->char('currency', 3)->default('EGP');
                $table->string('unit_code', 40)->nullable();
                $table->string('unit_type', 80)->nullable();
                $table->decimal('unit_area', 10, 2)->nullable();
                $table->string('unit_location', 255)->nullable();
                $table->string('contact_representative', 160)->nullable();
                $table->string('field_agent_name', 160)->nullable();
                $table->text('notes')->nullable();
                if ($section === 'rental') {
                    $table->string('rental_term', 20)->nullable();
                    $table->unsignedSmallInteger('rental_term_count')->nullable();
                    $table->string('payment_schedule', 30)->nullable();
                } else {
                    $table->string('seller_name', 160)->nullable();
                    $table->string('seller_phone', 30)->nullable();
                }
                $table->timestamps();
                $table->index('deal_date');
            });

            Schema::create("{$section}_deal_commissions", function (Blueprint $table) use ($section): void {
                $table->id();
                $table->foreignId('deal_id')->constrained("{$section}_deals")->cascadeOnDelete();
                $table->string('payer', 160);
                $table->string('recipient', 160);
                $table->string('calculation_type', 16);
                $table->decimal('agreed_value', 15, 2);
                $table->text('conditions')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::dropIfExists("{$section}_deal_commissions");
            Schema::dropIfExists("{$section}_deals");
        }
    }
};
