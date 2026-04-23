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
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            if (!Schema::hasColumn('ganti_pisau_rotaries', 'jenis_kendala')) {
                $table->string('jenis_kendala')->nullable()->after('id_produksi');
            }

            // Cek dan tambah kolom keterangan
            if (!Schema::hasColumn('ganti_pisau_rotaries', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('jenis_kendala');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ganti_pisau_rotaries', function (Blueprint $table) {
            if (Schema::hasColumn('ganti_pisau_rotaries', 'jenis_kendala')) {
                $table->dropColumn('jenis_kendala');
            }

            if (Schema::hasColumn('ganti_pisau_rotaries', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
        });
    }
};
