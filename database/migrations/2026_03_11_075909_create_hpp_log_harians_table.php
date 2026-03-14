<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hpp_log_veneer_kering', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('id_ukuran')->constrained('ukurans');
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->string('kw', 10);

            // Ringkasan pergerakan stok hari ini
            $table->decimal('total_m3_masuk', 12, 6)->default(0);
            $table->decimal('total_m3_keluar', 12, 6)->default(0);
            $table->decimal('stok_akhir_m3', 12, 6)->default(0);

            // HPP akhir hari
            $table->decimal('hpp_veneer_basah_per_m3', 15, 4)->default(1000000);
            $table->decimal('avg_ongkos_dryer_per_m3', 15, 4)->default(0)
                ->comment('Rata-rata ongkos dryer dari semua produksi hari ini');
            $table->decimal('hpp_kering_per_m3', 15, 4)->default(0);
            $table->decimal('hpp_average', 15, 4)->default(0)
                ->comment('Moving average akhir hari ini');
            $table->decimal('nilai_stok_akhir', 20, 4)->default(0);

            $table->timestamps();

            // Satu baris per hari per kombinasi produk
            $table->unique(
                ['tanggal', 'id_ukuran', 'id_jenis_kayu', 'kw'],
                'uq_hpp_log_harian'
            );
            $table->index('tanggal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hpp_log_veneer_kering');
<<<<<<< HEAD

=======
>>>>>>> develop
    }
};
