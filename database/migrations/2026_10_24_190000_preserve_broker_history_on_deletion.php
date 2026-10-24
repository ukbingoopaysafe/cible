<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rental_requests' => 'rental_brokers', 'resale_requests' => 'resale_brokers'] as $tableName => $brokerTable) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('broker_name_snapshot')->nullable();
                $table->string('broker_phone_snapshot', 40)->nullable();
                $table->dropForeign(['broker_id']);
            });
            Schema::table($tableName, function (Blueprint $table) use ($brokerTable): void {
                $table->foreign('broker_id')->references('id')->on($brokerTable)->nullOnDelete();
            });
        }

        foreach (['rental_activities', 'resale_activities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('broker_id_snapshot')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['rental_requests' => 'rental_brokers', 'resale_requests' => 'resale_brokers'] as $tableName => $brokerTable) {
            Schema::table($tableName, function (Blueprint $table) use ($brokerTable): void {
                $table->dropForeign(['broker_id']);
                $table->foreign('broker_id')->references('id')->on($brokerTable)->restrictOnDelete();
                $table->dropColumn(['broker_name_snapshot', 'broker_phone_snapshot']);
            });
        }
        foreach (['rental_activities', 'resale_activities'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_broker_id_snapshot_index');
                $table->dropColumn('broker_id_snapshot');
            });
        }
    }
};
