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
        Schema::table('attendance', function (Blueprint $table) {
            $table->time('morning_time_in')->nullable()->after('time_out');
            $table->time('morning_time_out')->nullable()->after('morning_time_in');
            $table->time('afternoon_time_in')->nullable()->after('morning_time_out');
            $table->time('afternoon_time_out')->nullable()->after('afternoon_time_in');
            $table->time('overtime_time_in')->nullable()->after('afternoon_time_out');
            $table->time('overtime_time_out')->nullable()->after('overtime_time_in');
            $table->boolean('is_overtime')->default(false)->after('overtime_time_out');
            $table->decimal('overtime_hours', 5, 2)->nullable()->after('is_overtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn([
                'morning_time_in',
                'morning_time_out',
                'afternoon_time_in',
                'afternoon_time_out',
                'overtime_time_in',
                'overtime_time_out',
                'is_overtime',
                'overtime_hours',
            ]);
        });
    }
};
