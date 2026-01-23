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
        Schema::create('hasil_pilih_plywood', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_pilih_plywood')
                ->constrained('produksi_pilih_plywood')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('id_barang_setengah_jadi_hp')
                ->nullable()
                ->constrained('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->enum('jenis_cacat',['mengelupas','pecah','delaminasi/melembung','kropos','dll']);
            $table->integer('jumlah');
            $table->integer('jumlah_bagus');
            $table->enum('kondisi',['reject','reparasi','selesai']);
            $table->string('ket')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_pilih_plywood');
    }
};
