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
        Schema::create('employee_warnings_memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['warning', 'memo']);
            $table->enum('reason_type', ['late_warning', 'late_memo', 'absent_warning', 'absent_memo']);
            $table->text('notes')->nullable();
            $table->foreignId('sent_by')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('related_month')->nullable();
            $table->unsignedInteger('related_year')->nullable();
            $table->unsignedInteger('count_at_time')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('employee_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('employee_id');
            $table->index('type');
            $table->index('reason_type');
            $table->index('acknowledged_at');
            $table->index('resolved_at');
            $table->index(['employee_id', 'reason_type', 'related_month', 'related_year', 'resolved_at'], 'employee_reason_period_resolved_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_warnings_memos');
    }
};
