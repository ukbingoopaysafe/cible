<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental', 'resale'] as $section) {
            Schema::table("{$section}_field_inspections", function (Blueprint $table) use ($section): void {
                $table->string('contact_role', 16)->nullable();
                $table->string('contact_name', 255)->nullable();
                $table->string('contact_phone_e164', 16)->nullable();
                $table->char('contact_phone_country', 2)->nullable();
                $table->foreignId('linked_broker_id')
                    ->nullable()
                    ->constrained("{$section}_brokers")
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Refusing to remove apartment inspection contact snapshots; they are permanent request history.');
    }
};
