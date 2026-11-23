<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('designation')->nullable()->after('status');
            $table->string('avatar_path')->nullable()->after('designation');
        });

        Schema::table('employees', function (Blueprint $table): void {
            $table->time('duty_start_time')->nullable()->after('status');
            $table->time('duty_end_time')->nullable()->after('duty_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn(['duty_start_time', 'duty_end_time']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['designation', 'avatar_path']);
        });
    }
};
