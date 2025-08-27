<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('headings', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();

            // Lưu URL gốc để hiển thị; KHÔNG index toàn bộ vì quá dài
            $table->text('url');
            // Dùng hash để ràng buộc duy nhất / index
            $table->char('url_hash', 64)->index();   // sha1(lowercase(url))

            $table->enum('level', ['h2','h3']);      // MVP chỉ H2/H3
            $table->text('text');
            $table->char('text_hash', 64)->index();  // sha1(lowercase(norm_text))
            $table->boolean('is_focus')->default(false);

            $table->timestamps();

            $table->unique(['run_id','url_hash','level','text_hash'], 'headings_unique_compact');
            $table->index(['run_id','level']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('headings');
    }
};
