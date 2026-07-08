<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            // Hapus kolom 'tujuan' kalau migration sebelumnya sudah sempat dijalankan.
            if (Schema::hasColumn('serah_terima_hp', 'tujuan')) {
                $table->dropColumn('tujuan');
            }

            $table->unsignedBigInteger('id_hasil_graji_triplek')->nullable()->after('id_platform_hasil_hp');
            $table->unsignedBigInteger('id_hasil_sanding')->nullable()->after('id_hasil_graji_triplek');

            $table->foreign('id_hasil_graji_triplek')
                ->references('id')->on('hasil_graji_triplek')
                ->nullOnDelete();

            $table->foreign('id_hasil_sanding')
                ->references('id')->on('hasil_sandings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropForeign(['id_hasil_graji_triplek']);
            $table->dropForeign(['id_hasil_sanding']);
            $table->dropColumn(['id_hasil_graji_triplek', 'id_hasil_sanding']);
        });
    }
};
