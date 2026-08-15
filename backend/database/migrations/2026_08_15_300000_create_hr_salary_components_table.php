<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_salary_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 30)->unique();
            $table->enum('type', ['earning', 'deduction']);
            $table->enum('calculation', ['fixed', 'percentage']);  // fixed USD or % of base salary
            $table->decimal('value', 10, 4);                       // USD amount or % value (e.g. 5.0 = 5%)
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_salary_components');
    }
};
