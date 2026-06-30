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
        Schema::create('ongkos_produksi_dryers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_dryer')
                ->unique()
                ->constrained('produksi_press_dryers')
                ->cascadeOnDelete();

            // Data dari produksi (dihitung otomatis oleh service)
            $table->decimal('total_m3', 12, 6)->default(0);
            $table->integer('ttl_pekerja')->default(0)
                ->comment('Pekerja hadir, tidak ijin');
            $table->integer('jumlah_mesin')->default(0);

            // Tarif (bisa di-override per sesi sebelum final)
            $table->decimal('tarif_per_pekerja', 12, 2)->default(115000);
            $table->decimal('tarif_per_mesin', 12, 2)->default(335000);

            // Hasil kalkulasi — dihitung via service, bukan stored as
            $table->decimal('ongkos_pekerja', 15, 2)->default(0);
            $table->decimal('ongkos_mesin', 15, 2)->default(0);
            $table->decimal('total_ongkos', 15, 2)->default(0);
            $table->decimal('ongkos_per_m3', 15, 4)->nullable()
                ->comment('Ongkos per m3 = total_ongkos / total_m3');

            $table->boolean('is_final')->default(false)
                ->comment('Jika true, tarif tidak bisa diubah lagi');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ongkos_produksi_dryers');
    }
};
