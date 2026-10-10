<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->timestamp('diterima_gudang_at')->nullable();
            $table->string('diterima_gudang_oleh')->nullable();
        });

        // Data lama sudah menambah stok saat diterima produksi,
        // tandai supaya tidak ketambah dua kali.
        DB::table('serah_terima_hp')
            ->where('diterima_oleh', '!=', '-')
            ->where(fn ($q) => $q->whereNotNull('id_triplek_hasil_hp')
                ->orWhereNotNull('id_platform_hasil_hp'))
            ->update(['diterima_gudang_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropColumn(['diterima_gudang_at', 'diterima_gudang_oleh']);
        });
    }
};
