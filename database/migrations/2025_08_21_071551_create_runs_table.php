<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->string('query', 1024);
            $table->enum('mode', ['domain','entity','free_text'])->default('free_text');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('runs');
    }
};
