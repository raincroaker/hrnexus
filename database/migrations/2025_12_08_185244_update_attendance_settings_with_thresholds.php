<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedInteger('late_threshold_warning')->default(3);
            $table->unsignedInteger('late_threshold_memo')->default(5);
            $table->unsignedInteger('absent_threshold_warning')->default(3);
            $table->unsignedInteger('absent_threshold_memo')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn([
                'late_threshold_warning',
                'late_threshold_memo',
                'absent_threshold_warning',
                'absent_threshold_memo',
            ]);
        });
    }
};
