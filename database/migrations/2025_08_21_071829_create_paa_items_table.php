<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('paa_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->text('question');                 // câu PAA
            $table->string('question_hash', 64)->index();
            $table->enum('intent', ['informational','navigational','transactional'])->nullable();
            $table->unsignedInteger('freq')->default(1);
            $table->timestamps();

            $table->unique(['run_id','question_hash']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('paa_items');
    }
};
