<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->string('tujuan')->nullable()->after('id_produksi_sanding');
            // isi: 'sanding', 'gudang', 'graji_triplek', dst — sesuai kebutuhan
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropColumn('tujuan');
        });
    }
};
