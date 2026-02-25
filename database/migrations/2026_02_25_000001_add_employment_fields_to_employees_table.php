<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('hire_date')->nullable()->after('birth_date');
            $table->enum('employment_status', ['active', 'inactive'])->default('active')->after('role');
            $table->enum('inactive_reason', ['terminated', 'resigned', 'retired', 'end_of_contract', 'other'])->nullable()->after('employment_status');
            $table->text('inactive_reason_notes')->nullable()->after('inactive_reason');
            $table->date('inactive_date')->nullable()->after('inactive_reason_notes');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'hire_date',
                'employment_status',
                'inactive_reason',
                'inactive_reason_notes',
                'inactive_date',
            ]);
        });
    }
};
