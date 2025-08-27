<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();

            // Lưu URL gốc để hiển thị, KHÔNG index toàn bộ để tránh quá giới hạn
            $table->text('url');
            // Dùng hash để ràng buộc duy nhất / index
            $table->char('url_hash', 64)->index(); // sha1(lower(url_normalized))

            $table->text('question');
            $table->char('question_hash', 64)->index(); // sha1(lower(norm_question))
            $table->text('answer')->nullable();
            $table->char('answer_hash', 64)->nullable()->index();

            $table->timestamps();

            
            $table->unique(['run_id', 'url_hash', 'question_hash'], 'faq_items_unique_compact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
