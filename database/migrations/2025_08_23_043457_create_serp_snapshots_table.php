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
        Schema::create('serp_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $table->string('query_used', 1024)->index();   
            $table->text('url');                           
            $table->char('url_hash', 64)->index();         
            $table->unsignedInteger('position')->nullable();
            $table->boolean('has_faq_rich')->default(false);
            $table->timestamps();

            $table->index(['run_id', 'position']);
            $table->unique(['run_id', 'url_hash', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serp_snapshots');
    }
};
