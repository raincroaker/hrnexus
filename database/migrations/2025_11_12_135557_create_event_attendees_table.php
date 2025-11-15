<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // attendee
            $table->foreignId('event_id')->constrained('calendar_events')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'event_id']); // prevent duplicate attendance
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_attendees');
    }
};

