<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $areas = [
            'rental_areas' => [
                ['governorate' => 'القاهرة', 'name' => 'التجمع الخامس'],
                ['governorate' => 'القاهرة', 'name' => 'الزمالك'],
                ['governorate' => 'القاهرة', 'name' => 'المعادي'],
                ['governorate' => 'القاهرة', 'name' => 'مدينة نصر'],
                ['governorate' => 'القاهرة', 'name' => 'مدينتي'],
                ['governorate' => 'الجيزة', 'name' => 'الجيزة'],
                ['governorate' => 'الجيزة', 'name' => '6 أكتوبر'],
                ['governorate' => 'الجيزة', 'name' => 'حدائق أكتوبر'],
                ['governorate' => 'الجيزة', 'name' => 'الشيخ زايد'],
                ['governorate' => 'الجيزة', 'name' => 'حدائق الأهرام'],
                ['governorate' => 'الجيزة', 'name' => 'الهرم'],
                ['governorate' => 'الجيزة', 'name' => 'فيصل'],
                ['governorate' => 'الجيزة', 'name' => 'الدقي'],
                ['governorate' => 'الجيزة', 'name' => 'العجوزة'],
                ['governorate' => 'الجيزة', 'name' => 'المهندسين'],
            ],
        ];
        $areas['resale_areas'] = $areas['rental_areas'];

        foreach ($areas as $tableName => $values) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('governorate', 40);
                $table->string('name', 100);
                $table->timestamps();
                $table->unique(['governorate', 'name']);
            });

            $now = now();
            DB::table($tableName)->insert(array_map(
                static fn (array $area): array => [...$area, 'created_at' => $now, 'updated_at' => $now],
                $values,
            ));
        }

        foreach (['rental_brokers', 'resale_brokers'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('phone_e164', 16)->nullable()->unique();
                $table->char('phone_country', 2)->nullable();
                $table->timestamps();
            });
        }

        foreach (['rental_lead_sources', 'resale_lead_sources'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('is_other')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            $now = now();
            DB::table($tableName)->insert([
                'name' => 'أخرى',
                'is_other' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $closeReasons = [
            ['key' => 'budget_mismatch', 'name' => 'الميزانية غير مناسبة', 'is_other' => false, 'sort_order' => 10],
            ['key' => 'area_mismatch', 'name' => 'المنطقة غير مناسبة', 'is_other' => false, 'sort_order' => 20],
            ['key' => 'requirements_mismatch', 'name' => 'المواصفات غير مناسبة', 'is_other' => false, 'sort_order' => 30],
            ['key' => 'no_suitable_unit', 'name' => 'لا توجد وحدة مناسبة', 'is_other' => false, 'sort_order' => 40],
            ['key' => 'no_response', 'name' => 'العميل لا يرد', 'is_other' => false, 'sort_order' => 50],
            ['key' => 'client_changed_plans', 'name' => 'العميل غيّر خطته', 'is_other' => false, 'sort_order' => 60],
            ['key' => 'chose_other_option', 'name' => 'اختار بديلًا آخر', 'is_other' => false, 'sort_order' => 70],
            ['key' => 'duplicate_request', 'name' => 'طلب مكرر', 'is_other' => false, 'sort_order' => 80],
            ['key' => 'other', 'name' => 'أخرى', 'is_other' => true, 'sort_order' => 90],
        ];

        foreach (['rental_request_close_reasons', 'resale_request_close_reasons'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->string('key', 50)->unique();
                $table->string('name', 120);
                $table->boolean('is_other')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table($tableName)->insert(array_map(
                static fn (array $reason): array => [...$reason, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                $closeReasons,
            ));
        }

        Schema::create('rental_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('rental_customers')->restrictOnDelete();
            $table->foreignId('broker_id')->nullable()->constrained('rental_brokers')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('rental_areas')->restrictOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('rental_lead_sources')->restrictOnDelete();
            $table->foreignId('outcome_reason_id')->nullable()->constrained('rental_request_close_reasons')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_party', 16);
            $table->string('lead_source_other')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('stage', 24)->default('intake');
            $table->dateTime('next_follow_up_at')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->string('outcome_reason_other')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('unit_type', 40)->nullable();
            $table->string('unit_type_other')->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('occupants')->nullable();
            $table->boolean('household')->nullable();
            $table->string('rental_term', 20)->nullable();
            $table->unsignedSmallInteger('rental_term_count')->nullable();
            $table->string('finishing', 40)->nullable();
            $table->string('finishing_other')->nullable();
            $table->string('floor_preference', 40)->nullable();
            $table->boolean('elevator_required')->nullable();
            $table->text('other_requirements')->nullable();
            $table->decimal('budget_declared', 15, 2)->nullable();
            $table->decimal('budget_targeted', 15, 2)->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_follow_up_at']);
            $table->index(['owner_user_id', 'status']);
        });

        Schema::create('resale_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('resale_customers')->restrictOnDelete();
            $table->foreignId('broker_id')->nullable()->constrained('resale_brokers')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('resale_areas')->restrictOnDelete();
            $table->foreignId('lead_source_id')->nullable()->constrained('resale_lead_sources')->restrictOnDelete();
            $table->foreignId('outcome_reason_id')->nullable()->constrained('resale_request_close_reasons')->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('contact_party', 16);
            $table->string('lead_source_other')->nullable();
            $table->string('status', 16)->default('active');
            $table->string('stage', 24)->default('intake');
            $table->dateTime('next_follow_up_at')->nullable();
            $table->string('outcome', 20)->nullable();
            $table->string('outcome_reason_other')->nullable();
            $table->text('outcome_notes')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('unit_type', 40)->nullable();
            $table->decimal('area_min', 10, 2)->nullable();
            $table->decimal('area_max', 10, 2)->nullable();
            $table->decimal('budget_declared', 15, 2)->nullable();
            $table->decimal('budget_targeted', 15, 2)->nullable();
            $table->char('budget_currency', 3)->nullable();
            $table->text('other_requirements')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_follow_up_at']);
            $table->index(['owner_user_id', 'status']);
        });

        foreach (['rental_request_stage_histories' => 'rental_requests', 'resale_request_stage_histories' => 'resale_requests'] as $historyTable => $requestTable) {
            Schema::create($historyTable, function (Blueprint $table) use ($requestTable): void {
                $table->id();
                $table->foreignId('request_id')->constrained($requestTable)->restrictOnDelete();
                $table->string('from_stage', 24)->nullable();
                $table->string('to_stage', 24);
                $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['request_id', 'created_at']);
            });
        }

        foreach (['rental_request_status_histories' => 'rental_requests', 'resale_request_status_histories' => 'resale_requests'] as $historyTable => $requestTable) {
            Schema::create($historyTable, function (Blueprint $table) use ($requestTable): void {
                $table->id();
                $table->foreignId('request_id')->constrained($requestTable)->restrictOnDelete();
                $table->string('from_status', 16)->nullable();
                $table->string('to_status', 16);
                $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['request_id', 'created_at']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('resale_request_status_histories');
        Schema::dropIfExists('rental_request_status_histories');
        Schema::dropIfExists('resale_request_stage_histories');
        Schema::dropIfExists('rental_request_stage_histories');
        Schema::dropIfExists('resale_requests');
        Schema::dropIfExists('rental_requests');
        Schema::dropIfExists('resale_request_close_reasons');
        Schema::dropIfExists('rental_request_close_reasons');
        Schema::dropIfExists('resale_lead_sources');
        Schema::dropIfExists('rental_lead_sources');
        Schema::dropIfExists('resale_brokers');
        Schema::dropIfExists('rental_brokers');
        Schema::dropIfExists('resale_areas');
        Schema::dropIfExists('rental_areas');
    }
};
