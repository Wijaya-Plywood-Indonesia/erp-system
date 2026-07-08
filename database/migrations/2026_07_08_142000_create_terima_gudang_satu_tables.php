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
        Schema::create('produksi_terima_gudang_satu', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_produksi');
            $table->string('kendala')->nullable();
            $table->timestamps();
        });

        Schema::create('bahan_terima_gudang_satu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu');
            $table->unsignedBigInteger('id_barang_setengah_jadi_hp');
            $table->integer('no_palet');
            $table->integer('jumlah');
            $table->timestamps();

            $table->foreign('id_produksi_terima_gudang_satu', 'fk_bahan_tg_satu_prod')
                ->references('id')
                ->on('produksi_terima_gudang_satu')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_barang_setengah_jadi_hp', 'fk_bahan_tg_satu_barang')
                ->references('id')
                ->on('barang_setengah_jadi_hp')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('pegawai_terima_gudang_satu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu');
            $table->unsignedBigInteger('id_pegawai');
            $table->time('masuk');
            $table->time('pulang');
            $table->text('tugas');
            $table->string('ijin')->nullable();
            $table->string('ket')->nullable();
            $table->timestamps();

            $table->foreign('id_produksi_terima_gudang_satu', 'fk_pegawai_tg_satu_prod')
                ->references('id')
                ->on('produksi_terima_gudang_satu')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_pegawai', 'fk_pegawai_tg_satu_pegawai')
                ->references('id')
                ->on('pegawais')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('validasi_terima_gudang_satu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu');
            $table->string('role');
            $table->string('status');
            $table->timestamps();

            $table->foreign('id_produksi_terima_gudang_satu', 'fk_validasi_tg_satu_prod')
                ->references('id')
                ->on('produksi_terima_gudang_satu')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('hasil_terima_gudang_satu', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produksi_terima_gudang_satu');
            $table->unsignedBigInteger('id_grade');
            $table->unsignedBigInteger('id_jenis_barang');
            $table->unsignedBigInteger('id_ukuran');
            $table->integer('jumlah');
            $table->string('ket')->nullable();
            $table->timestamps();

            $table->foreign('id_produksi_terima_gudang_satu', 'fk_hasil_tg_satu_prod')
                ->references('id')
                ->on('produksi_terima_gudang_satu')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_grade', 'fk_hasil_tg_satu_grade')
                ->references('id')
                ->on('grades')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_jenis_barang', 'fk_hasil_tg_satu_jenis')
                ->references('id')
                ->on('jenis_barang')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreign('id_ukuran', 'fk_hasil_tg_satu_ukuran')
                ->references('id')
                ->on('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_terima_gudang_satu');
        Schema::dropIfExists('validasi_terima_gudang_satu');
        Schema::dropIfExists('pegawai_terima_gudang_satu');
        Schema::dropIfExists('bahan_terima_gudang_satu');
        Schema::dropIfExists('produksi_terima_gudang_satu');
    }
};
