<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_triplek_jadis', function (Blueprint $table) {
            $table->id();

            // Asal barang: 'produksi' | 'repair' | 'bm' | dst.
            // Disimpan eksplisit supaya branching di halaman tidak perlu menebak.
            $table->string('source', 30)->default('produksi')->index();

            // Snapshot spesifikasi barang saat masuk antrean
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->decimal('panjang', 10, 2);
            $table->decimal('lebar', 10, 2);
            $table->decimal('tebal', 10, 2);
            $table->string('kw_grade', 50);

            $table->integer('stok_lembar');
            $table->decimal('stok_kubikasi', 15, 6)->default(0);

            // Snapshot HPP saat masuk antrean (boleh 0 dulu bila belum dihitung)
            $table->decimal('nilai_stok', 18, 2)->default(0);
            $table->decimal('hpp_pekerja_last', 18, 2)->default(0);
            $table->decimal('hpp_bahan_penolong_last', 18, 2)->default(0);

            // Referensi polimorfik ke sumber hulu (ProduksiTriplek, Repair, NotaBM, dll.)
            $table->nullableMorphs('referensi');

            $table->text('keterangan')->nullable();

            // Status serah terima
            $table->string('status_gudang', 30)->default('belum diterima')->index();
            $table->foreignId('diterima_by')->nullable()->constrained('users');
            $table->timestamp('diterima_at')->nullable();

            $table->timestamps();

            $table->index(
                ['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade'],
                'gtj_spesifikasi_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_triplek_jadis');
    }
};
