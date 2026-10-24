<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental' => 'rental_customers', 'resale' => 'resale_customers'] as $section => $customerTable) {
            Schema::create("{$section}_aftercare", function (Blueprint $table) use ($customerTable): void {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained($customerTable)->restrictOnDelete();
                $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
                $table->dateTime('next_follow_up_at')->nullable();
                $table->timestamps();
                $table->index('next_follow_up_at');
            });

            Schema::create("{$section}_aftercare_activities", function (Blueprint $table) use ($section): void {
                $table->id();
                $table->foreignId('aftercare_id')->constrained("{$section}_aftercare")->restrictOnDelete();
                $table->foreignId('deal_id')->nullable()->constrained("{$section}_deals")->restrictOnDelete();
                $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
                $table->string('channel', 24);
                $table->dateTime('occurred_at');
                $table->text('result');
                $table->text('notes')->nullable();
                $table->text('referral_details')->nullable();
                $table->dateTime('next_follow_up_at')->nullable();
                $table->timestamps();
                $table->index(['aftercare_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::dropIfExists("{$section}_aftercare_activities");
            Schema::dropIfExists("{$section}_aftercare");
        }
    }
};
