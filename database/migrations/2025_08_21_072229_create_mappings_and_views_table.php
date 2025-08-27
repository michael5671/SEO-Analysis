<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Bảng map PAA ↔ Heading (khi similarity ≥ threshold)
        Schema::create('map_paa_headings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->foreignId('paa_item_id')->constrained('paa_items')->cascadeOnDelete();
            $table->foreignId('heading_id')->constrained('headings')->cascadeOnDelete();
            $table->float('similarity')->default(0); // 0..1 (ví dụ Jaccard)
            $table->timestamps();

            $table->unique(['paa_item_id','heading_id']); // 1 cặp chỉ ghi 1 lần
            $table->index(['run_id','similarity']);
        });

        // Bảng map Heading ↔ FAQ (heading “được tái sử dụng” thành câu FAQ)
        Schema::create('map_heading_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->foreignId('heading_id')->constrained('headings')->cascadeOnDelete();
            $table->foreignId('faq_item_id')->constrained('faq_items')->cascadeOnDelete();
            $table->float('similarity')->default(0);
            $table->timestamps();

            $table->unique(['heading_id','faq_item_id']);
            $table->index(['run_id','similarity']);
        });

        // ===== Views tính % chuyển đổi theo run_id =====

        // 1) PAA → Subheadings
        DB::statement(/** @lang SQL */ "
            CREATE VIEW view_paa_to_heading_conversion AS
            SELECT
                r.id AS run_id,
                COALESCE(p.total_paa, 0) AS total_paa,
                COALESCE(m.matched_paa, 0) AS matched_paa,
                CASE
                    WHEN COALESCE(p.total_paa,0) = 0 THEN 0
                    ELSE ROUND(100.0 * COALESCE(m.matched_paa,0) / p.total_paa, 2)
                END AS pct_paa_to_heading
            FROM runs r
            LEFT JOIN (
                SELECT run_id, COUNT(DISTINCT id) AS total_paa
                FROM paa_items
                GROUP BY run_id
            ) p ON p.run_id = r.id
            LEFT JOIN (
                SELECT run_id, COUNT(DISTINCT paa_item_id) AS matched_paa
                FROM map_paa_headings
                GROUP BY run_id
            ) m ON m.run_id = r.id
        ");

        // 2) Subheadings → FAQ
        DB::statement(/** @lang SQL */ "
            CREATE VIEW view_heading_to_faq_conversion AS
            SELECT
                r.id AS run_id,
                COALESCE(h.total_headings, 0) AS total_headings,
                COALESCE(m.matched_headings, 0) AS matched_headings,
                CASE
                    WHEN COALESCE(h.total_headings,0) = 0 THEN 0
                    ELSE ROUND(100.0 * COALESCE(m.matched_headings,0) / h.total_headings, 2)
                END AS pct_heading_to_faq
            FROM runs r
            LEFT JOIN (
                SELECT run_id, COUNT(DISTINCT id) AS total_headings
                FROM headings
                GROUP BY run_id
            ) h ON h.run_id = r.id
            LEFT JOIN (
                SELECT run_id, COUNT(DISTINCT heading_id) AS matched_headings
                FROM map_heading_faqs
                GROUP BY run_id
            ) m ON m.run_id = r.id
        ");
    }

    public function down(): void {
        DB::statement("DROP VIEW IF EXISTS view_heading_to_faq_conversion");
        DB::statement("DROP VIEW IF EXISTS view_paa_to_heading_conversion");
        Schema::dropIfExists('map_heading_faqs');
        Schema::dropIfExists('map_paa_headings');
    }
};
