<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bk', function (Blueprint $t) {
            $t->id();
            $t->uuid('siklus_uuid')->index();
            $t->string('kategori', 10);                 // bk | bkk | bkl
            $t->string('no_nota')->unique();
            $t->date('tanggal');
            $t->string('tujuan_nota');
            $t->string('alamat')->nullable();
            $t->text('keterangan')->nullable();
            $t->string('status', 20)->default('draft'); // draft | divalidasi | selesai
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('divalidasi_at')->nullable();
            $t->timestamps();
            $t->index(['kategori', 'status']);
        });

        Schema::create('bk_detail', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_bk')->constrained('bk')->cascadeOnDelete();
            $t->string('nama_barang');
            $t->decimal('jumlah', 15, 3);
            $t->string('satuan')->nullable();
            $t->string('keterangan')->nullable();
            $t->timestamps();
        });

        Schema::create('bm', function (Blueprint $t) {
            $t->id();
            $t->uuid('siklus_uuid')->index();
            $t->string('kategori', 10);                 // bm | bmk | bml
            $t->string('no_nota')->unique();
            $t->date('tanggal');
            $t->string('dari')->nullable();
            $t->foreignId('id_bk_referensi')->nullable()->constrained('bk')->nullOnDelete();
            $t->text('keterangan')->nullable();
            $t->string('status', 20)->default('draft'); // draft | divalidasi
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('divalidasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('divalidasi_at')->nullable();
            $t->timestamps();
        });

        Schema::create('bm_detail', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_bm')->constrained('bm')->cascadeOnDelete();
            $t->string('nama_barang');
            $t->decimal('jumlah', 15, 3);
            $t->string('satuan')->nullable();
            $t->string('keterangan')->nullable();
            $t->timestamps();
        });

        // Penghitung nomor (dikunci saat dipakai agar tidak kembar).
        Schema::create('bkbm_counter', function (Blueprint $t) {
            $t->string('tipe', 10)->primary();
            $t->unsignedInteger('terakhir')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkbm_counter');
        Schema::dropIfExists('bm_detail');
        Schema::dropIfExists('bm');
        Schema::dropIfExists('bk_detail');
        Schema::dropIfExists('bk');
    }
};
