<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // uploader
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null'); // optional
            $table->string('file_name');
            $table->string('file_type'); // e.g., pdf, docx
            $table->string('file_path'); // storage path
            $table->bigInteger('size')->nullable(); // size in bytes
            $table->text('description')->nullable();
            $table->longText('content')->nullable(); // text content if needed
            $table->longText('embedding')->nullable(); // for AI embedding
            $table->enum('accessibility', ['public', 'private', 'department'])->default('public');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
