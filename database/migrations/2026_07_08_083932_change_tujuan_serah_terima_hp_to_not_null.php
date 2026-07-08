<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill kolom `tujuan` berdasarkan kolom id_* mana yang terisi,
        // mengikuti logic yang sama dengan penentuan asal di SerahTerimaHp model.
        DB::table('serah_terima_hp')
            ->whereNull('tujuan')
            ->whereNotNull('id_triplek_hasil_hp')
            ->update(['tujuan' => 'graji_triplek']);

        DB::table('serah_terima_hp')
            ->whereNull('tujuan')
            ->whereNotNull('id_platform_hasil_hp')
            ->update(['tujuan' => 'sanding']);

        DB::table('serah_terima_hp')
            ->whereNull('tujuan')
            ->whereNotNull('id_hasil_graji_triplek')
            ->update(['tujuan' => 'sanding']);

        DB::table('serah_terima_hp')
            ->whereNull('tujuan')
            ->whereNotNull('id_hasil_sanding')
            ->update(['tujuan' => 'graji_triplek']);

        // Jaga-jaga kalau masih ada yang NULL (data anomali / tidak match kondisi manapun)
        DB::table('serah_terima_hp')
            ->whereNull('tujuan')
            ->update(['tujuan' => 'unknown']);

        // Baru ubah kolom jadi NOT NULL
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->string('tujuan')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->string('tujuan')->nullable()->change();
        });
    }
};
