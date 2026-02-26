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
        Schema::create('log_masuks', function (Blueprint $table) {
            $table->id();
            $table->date('tgl_masuk');
            $table->double('qty', 15, 4); // Misal: Kubikasi
            $table->timestamps();
        });

        Schema::create('hasil_produksis', function (Blueprint $table) {
            $table->id();
            $table->date('tgl_produksi');
            $table->double('qty_keluar', 15, 4);
            $table->timestamps();
        });
    }    /**
         * Reverse the migrations.
         */
    public function down(): void
    {
        Schema::dropIfExists('percobaan_tabel_produksi');
    }
};
