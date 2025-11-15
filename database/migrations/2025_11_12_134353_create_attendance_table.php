<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable(); // e.g. 8.50 hours
            $table->enum('status', ['Present', 'Late', 'Incomplete'])->default('Present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']); // Prevent duplicate attendance records per day
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
