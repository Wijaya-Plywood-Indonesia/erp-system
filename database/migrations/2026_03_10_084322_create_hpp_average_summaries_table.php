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
        Schema::create('hpp_average_summaries', function (Blueprint $table) {
            $table->id();

            // ── Kombinasi unik ────────────────────────────────────────────────
            $table->foreignId('id_lahan')
                ->constrained('lahans')
                ->cascadeOnDelete();

            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnDelete();

            $table->string('grade', 5)->nullable(); // Casting to char A, B, Etc

            $table->integer('panjang')->nullable();

            $table->integer('stok_batang')->default(0)->nullable();

            $table->decimal('stok_kubikasi', 15, 4)->default(0)->nullable();

            // Nilai stok dalam Rupiah (presisi 4 untuk hasil perkalian floating)
            $table->decimal('nilai_stok', 20, 2)->default(0)->nullable();

            // HPP Average per m³ — diupdate setiap ada masuk
            $table->decimal('hpp_average', 20, 2)->default(0)->nullable();

            // ── Referensi ke log terakhir ─────────────────────────────────────
            $table->foreignId('id_last_log')
                ->nullable()
                ->constrained('hpp_average_logs')
                ->nullOnDelete();

            $table->timestamps();

            // Unique: satu baris per kombinasi lahan+jenis+grade+panjang
            $table->unique(
                ['id_lahan', 'id_jenis_kayu', 'grade', 'panjang'],
                'uq_hpp_summary_kombinasi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hpp_average_summaries');
    }
};
