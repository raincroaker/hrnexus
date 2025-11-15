<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('biometric_logs', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code'); // matches Employee::employee_code
            $table->timestamp('scan_time');
            $table->timestamps();

            $table->index('employee_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_logs');
    }
};
