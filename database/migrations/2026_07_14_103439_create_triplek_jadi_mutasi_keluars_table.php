<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triplek_jadi_mutasi_keluars', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->decimal('panjang', 10, 2);
            $table->decimal('lebar', 10, 2);
            $table->decimal('tebal', 10, 2);
            $table->string('kw_grade', 50);

            $table->integer('jumlah_palet');
            $table->integer('stok_lembar');
            $table->decimal('stok_kubikasi', 15, 6)->default(0);

            $table->string('tujuan', 100);
            $table->foreignId('dikeluarkan_by')->nullable()->constrained('users');
            $table->text('keterangan')->nullable();

            // Status penerimaan di tujuan — stok baru dipotong saat 'diterima'
            $table->string('status', 30)->default('dikirim')->index();
            $table->foreignId('dikonfirmasi_by')->nullable()->constrained('users');
            $table->timestamp('dikonfirmasi_at')->nullable();

            $table->timestamps();
        });

        Schema::create('triplek_jadi_mutasi_keluar_palets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_mutasi_keluar')
                ->constrained('triplek_jadi_mutasi_keluars')
                ->cascadeOnDelete();
            $table->integer('nomor_palet');
            $table->integer('jumlah_lembar');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triplek_jadi_mutasi_keluar_palets');
        Schema::dropIfExists('triplek_jadi_mutasi_keluars');
    }
};
