<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_absensis', function (Blueprint $table) {
            $table->id();
            $table->time('jam_masuk_shift_pagi_default')->default('06:00:00');
            $table->time('jam_pulang_shift_pagi_default')->default('20:00:00');
            $table->time('jam_masuk_shift_malam_default')->default('16:00:00');
            $table->time('jam_pulang_shift_malam_default')->default('06:00:00');
            
            $table->integer('toleransi_sesi_tunggal_menit')->default(15);
            $table->integer('batas_total_durasi_menit')->default(60);
            $table->integer('toleransi_masuk_lebih_cepat_malam_menit')->default(300);
            $table->integer('toleransi_pulang_lebih_lambat_malam_menit')->default(300);
            
            $table->string('metode_shift_malam')->default('full_shift'); // 'default', 'paksa_shift_malam', 'full_shift'
            
            $table->boolean('auto_fix_enabled')->default(true);
            $table->integer('auto_fix_batas_selisih_menit')->default(60);
            
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_absensis');
    }
};
