<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->foreignId('id_hasil_sanding_joint')
                ->nullable()
                ->after('id_detail_bongkar_kedi')
                ->constrained('hasil_sanding_joint') // ← singular, sesuai nama tabel asli
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_hasil_sanding_joint');
        });
    }
};
