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
        Schema::create('list_pekerjaan_menumpuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pilih_plywood')
                ->constrained('produksi_pilih_plywood')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('tanggal');
            $table->foreignId('id_barang_setengah_jadi_hp')
                ->nullable()
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('list_pekerjaan_menumpuk');
    }
};
