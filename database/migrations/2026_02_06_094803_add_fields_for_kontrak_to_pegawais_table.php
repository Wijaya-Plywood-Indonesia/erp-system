<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            //
            $table->string('karyawan_di')->nullable()->after('tanggal_masuk');
            $table->string('alamat_perusahaan')->nullable()->after('karyawan_di');
            $table->string('jabatan')->nullable()->after('alamat_perusahaan');
            $table->string('nik')->nullable()->after('jabatan');
            $table->string('tempat_tanggal_lahir')->nullable()->after('nik');
            $table->string('scan_ktp')->nullable()->after('foto');
            $table->string('scan_kk')->nullable()->after('scan_ktp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawais', function (Blueprint $table) {
            //
            $table->dropColumn([
                'karyawan_di',
                'alamat_perusahaan',
                'jabatan',
                'nik',
                'tempat_tanggal_lahir',
                'scan_ktp',
                'scan_kk',
            ]);
        });
    }
};
