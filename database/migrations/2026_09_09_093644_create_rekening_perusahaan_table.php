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
        Schema::create('rekening_perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('pemilik_rekening', 255)->nullable();
            $table->string('nama_bank', 255)->nullable();
            $table->string('no_rekening', 255)->nullable();
            $table->string('atas_nama', 255)->nullable();
            $table->timestamps();
        });

        \Illuminate\Support\Facades\DB::table('rekening_perusahaan')->insert([
            [
                'id' => 1,
                'pemilik_rekening' => 'WIJAYA PLYWOOD INDONESIA',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-3939939',
                'atas_nama' => 'WIJAYA PLYWOOD INDONESIA',
                'created_at' => '2026-01-13 18:34:40',
                'updated_at' => '2026-08-26 06:24:23',
            ],
            [
                'id' => 2,
                'pemilik_rekening' => 'WAHANA PLYWOOD INDAH',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-9996696',
                'atas_nama' => 'WAHANA PLYWOOD INDAH',
                'created_at' => '2026-01-13 18:35:05',
                'updated_at' => '2026-08-26 06:21:59',
            ],
            [
                'id' => 3,
                'pemilik_rekening' => 'MELANI SUTANTY',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-0371250',
                'atas_nama' => 'MELANI SUTANTY',
                'created_at' => '2026-01-13 18:35:24',
                'updated_at' => '2026-08-26 06:22:15',
            ],
            [
                'id' => 4,
                'pemilik_rekening' => 'EDDY ANGKAWIJAYA',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-5399999',
                'atas_nama' => 'EDDY ANGKAWIJAYA',
                'created_at' => '2026-01-13 18:35:38',
                'updated_at' => '2026-07-04 04:31:31',
            ],
            [
                'id' => 5,
                'pemilik_rekening' => 'PT. INTAN NIAGA ABADI',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-6666639',
                'atas_nama' => 'PT. INTAN NIAGA ABADI',
                'created_at' => '2026-04-22 02:21:48',
                'updated_at' => '2026-08-26 06:22:30',
            ],
            [
                'id' => 6,
                'pemilik_rekening' => 'WIJAYA PLYWOOD INDUSTRI',
                'nama_bank' => 'BCA',
                'no_rekening' => '316-3333399',
                'atas_nama' => 'WIJAYA PLYWOOD INDUSTRI',
                'created_at' => '2026-07-04 04:32:46',
                'updated_at' => '2026-08-26 06:24:09',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekening_perusahaan');
    }
};
