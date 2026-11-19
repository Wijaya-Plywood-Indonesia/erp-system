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
        Schema::create('pengajuan_potongans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pegawai')->constrained('pegawais')->cascadeOnDelete();
            $table->string('kode_pegawai')->index();
            $table->date('tanggal')->index();
            $table->string('divisi')->nullable(); // Lini / Divisi produksi jika spesifik
            $table->decimal('nominal_awal', 15, 2)->default(0);
            $table->decimal('nominal_usulan', 15, 2)->default(0);
            $table->decimal('nominal_final', 15, 2)->nullable();
            $table->decimal('estimasi_gaji', 15, 2)->nullable();
            $table->text('alasan_pengajuan');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->index();
            $table->foreignId('diajukan_oleh')->constrained('users')->cascadeOnDelete();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_at')->nullable();
            $table->text('alasan_penolakan')->nullable();
            $table->timestamps();

            // Satu pengajuan pending per pegawai per tanggal (bisa juga per divisi jika ada)
            $table->index(['id_pegawai', 'tanggal', 'status']);
        });

        Schema::create('pengajuan_potongan_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_potongan_id')->constrained('pengajuan_potongans')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi'); // create, revise, approve, reject
            $table->decimal('nominal_sebelumnya', 15, 2)->nullable();
            $table->decimal('nominal_baru', 15, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengajuan_potongan_histories');
        Schema::dropIfExists('pengajuan_potongans');
    }
};
