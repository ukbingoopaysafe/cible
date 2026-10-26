<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_company_manager')->default(false)->after('password');
        });

        Schema::create('department_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('department', 16);
            $table->timestamps();
            $table->unique(['user_id', 'department']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_company_manager');
        });
    }
};
