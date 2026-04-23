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
        Schema::table('hpp_average_logs', function (Blueprint $table) {
            // 1. Tambahkan id_lahan (gunakan nullable agar tidak error constraint data seperti sebelumnya)
            $table->foreignId('id_lahan')
                ->after('id')
                ->nullable()
                ->constrained('lahans')
                ->cascadeOnDelete();

            // 2. Berikan indeks mandiri ke id_jenis_kayu 
            // Ini agar MySQL punya 'cadangan' indeks untuk Foreign Key
            $table->index('id_jenis_kayu', 'hpp_logs_jenis_kayu_index');
        });

        Schema::table('hpp_average_logs', function (Blueprint $table) {
            // 3. Sekarang MySQL sudah mengizinkan drop index lama
            $table->dropIndex('idx_hpp_log_kombinasi');

            // 4. Buat indeks komposit baru yang menyertakan lahan
            $table->index(
                ['id_lahan', 'id_jenis_kayu', 'grade', 'panjang', 'tanggal'],
                'idx_hpp_log_lahan_kombinasi'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hpp_average_logs', function (Blueprint $table) {
            $table->dropForeign(['id_lahan']);
            $table->dropColumn('id_lahan');
            $table->dropIndex('idx_hpp_log_lahan_kombinasi');

            // Kembalikan index lama jika diperlukan rollback
            $table->index(
                ['id_jenis_kayu', 'grade', 'panjang', 'tanggal', 'id'],
                'idx_hpp_log_kombinasi'
            );
        });
    }
};
