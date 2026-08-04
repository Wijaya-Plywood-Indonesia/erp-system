<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_barang', function (Blueprint $table) {
            $table->string('status_pengawas_produksi')->default('menunggu')->after('status_kepala_produksi');
            $table->foreignId('disetujui_pengawas_oleh')->nullable()->after('disetujui_kepala_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('disetujui_pengawas_at')->nullable()->after('disetujui_pengawas_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_barang', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disetujui_pengawas_oleh');
            $table->dropColumn(['status_pengawas_produksi', 'disetujui_pengawas_at']);
        });
    }
};