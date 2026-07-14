<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('detail_dempuls', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_triplek_cacat')
                ->nullable()
                ->after('id_barang_setengah_jadi_hp')
                ->constrained('serah_terima_triplek_cacat')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('detail_dempuls', function (Blueprint $table) {
            $table->dropForeign(['id_serah_terima_triplek_cacat']);
            $table->dropColumn('id_serah_terima_triplek_cacat');
        });
    }
};
