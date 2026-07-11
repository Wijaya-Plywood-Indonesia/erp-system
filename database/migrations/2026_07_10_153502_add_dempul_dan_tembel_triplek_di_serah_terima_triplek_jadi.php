<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_triplek_jadi', function (Blueprint $table) {
            $table->foreignId('id_detail_dempul')
                ->nullable()
                ->after('id_hasil_sanding')
                ->constrained('detail_dempuls')
                ->nullOnDelete();

            $table->foreignId('id_hasil_tembel_triplek')
                ->nullable()
                ->after('id_detail_dempul')
                ->constrained('hasil_tembel_triplek')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_triplek_jadi', function (Blueprint $table) {
            $table->dropForeign(['id_detail_dempul']);
            $table->dropColumn('id_detail_dempul');

            $table->dropForeign(['id_hasil_tembel_triplek']);
            $table->dropColumn('id_hasil_tembel_triplek');
        });
    }
};
