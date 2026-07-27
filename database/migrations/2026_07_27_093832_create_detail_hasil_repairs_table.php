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
        Schema::create('detail_hasil_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_produksi_repair')->constrained('produksi_repairs')->cascadeOnDelete();
            $table->foreignId('id_modal_repair')->nullable()->constrained('modal_repairs')->nullOnDelete();
            $table->foreignId('id_ukuran')->constrained('ukurans')->cascadeOnDelete();
            $table->string('kw');
            $table->integer('nomor_meja')->nullable();
            $table->integer('jumlah')->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamp('diserahkan_at')->nullable();
            $table->foreignId('diserahkan_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('detail_repair_pegawai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('detail_hasil_repair_id')->constrained('detail_hasil_repairs')->cascadeOnDelete();
            $table->foreignId('rencana_pegawai_repair_id')->constrained('rencana_pegawais')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_repair_pegawai');
        Schema::dropIfExists('detail_hasil_repairs');
    }
};
