<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('employee_code')->unique();
            $table->string('phone')->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('designation')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['region_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
