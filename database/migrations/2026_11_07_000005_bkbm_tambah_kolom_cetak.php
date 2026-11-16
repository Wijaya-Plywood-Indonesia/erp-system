<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bk', function (Blueprint $t) {
    if (!Schema::hasColumn('bk', 'metode_pembayaran')) {
        $t->string('metode_pembayaran', 50)->nullable();
    }

    if (!Schema::hasColumn('bk', 'id_rekening_perusahaan')) {
        $t->foreignId('id_rekening_perusahaan')
            ->nullable()
            ->constrained('rekening_perusahaan')
            ->nullOnDelete();
    }
});
    }

    public function down(): void
    {
        Schema::table('bk', function (Blueprint $t) {
            $t->dropConstrainedForeignId('id_rekening_perusahaan');
            $t->dropColumn('metode_pembayaran');
        });
        Schema::table('bk_detail', function (Blueprint $t) {
            $t->dropColumn(['harga', 'potongan', 'custom_nama', 'custom_m3']);
        });
    }
};
