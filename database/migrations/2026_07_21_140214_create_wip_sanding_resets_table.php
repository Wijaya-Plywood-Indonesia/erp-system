<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wip_sanding_resets', function (Blueprint $table) {
            $table->id();

            // Identitas spesifikasi (kunci gabungan jenis+ukuran+grade).
            $table->string('spec_key', 191)->index();

            // Rincian spesifikasi (untuk keperluan laporan/audit).
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $table->decimal('panjang', 10, 2);
            $table->decimal('lebar', 10, 2);
            $table->decimal('tebal', 10, 2);
            $table->string('kw_grade', 50);

            // Snapshot kumulatif SAAT reset ditekan — ini baseline-nya.
            // WIP setelah reset = (keluar_total − keluar_kumulatif)
            //                   − (masuk_total  − masuk_kumulatif).
            $table->decimal('keluar_kumulatif', 15, 2)->default(0);
            $table->decimal('masuk_kumulatif', 15, 2)->default(0);

            $table->foreignId('direset_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wip_sanding_resets');
    }
};