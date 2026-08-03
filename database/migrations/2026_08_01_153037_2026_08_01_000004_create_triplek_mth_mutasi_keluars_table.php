<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triplek_mth_mutasi_keluars', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();

            $table->decimal('panjang', 10, 2);
            $table->decimal('lebar', 10, 2);
            $table->decimal('tebal', 10, 2);
            $table->string('kw_grade')->nullable();

            $table->unsignedInteger('stok_lembar');
            $table->decimal('stok_kubikasi', 14, 6)->default(0);

            // Saat ini tujuan selalu 'Graji Triplek', kolom disiapkan untuk fleksibilitas ke depan.
            $table->string('tujuan')->default('Graji Triplek');

            $table->foreignId('dikeluarkan_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->index(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw_grade'], 'idx_triplek_mth_mutasi_keluar_dims');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triplek_mth_mutasi_keluars');
    }
};
