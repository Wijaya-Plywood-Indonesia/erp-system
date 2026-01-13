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
        Schema::create('detail_barang_dikerjakan_pot_siku', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pot_siku')
                    ->constrained('produksi_pot_siku')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            $table->foreignId('id_pegawai_pot_siku')
                ->constrained('pegawai_pot_siku')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_ukuran')
                ->nullable()
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_jenis_kayu')
                ->nullable()
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('kw');
            $table->integer('tinggi');
            $table->integer('no_palet');
            $table->integer('jumlah');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_barang_dikerjakan_pot_siku');
    }
};
