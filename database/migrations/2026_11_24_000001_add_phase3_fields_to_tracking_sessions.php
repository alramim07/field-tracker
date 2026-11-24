<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table): void {
            $table->string('session_type')->default('normal_duty')->after('stop_reason');
            $table->boolean('counted')->default(true)->after('session_type');
            $table->index(['employee_id', 'session_type', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tracking_sessions', function (Blueprint $table): void {
            $table->dropIndex(['employee_id', 'session_type', 'started_at']);
            $table->dropColumn(['session_type', 'counted']);
        });
    }
};
