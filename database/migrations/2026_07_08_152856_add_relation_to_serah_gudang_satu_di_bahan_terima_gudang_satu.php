<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bahan_terima_gudang_satu', function (Blueprint $table) {
            if (! Schema::hasColumn('bahan_terima_gudang_satu', 'id_serah_terima_gudang_satu')) {
                $table->foreignId('id_serah_terima_gudang_satu')
                    ->nullable()
                    ->after('id_produksi_terima_gudang_satu')
                    ->constrained('serah_terima_gudang_satu')
                    ->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bahan_terima_gudang_satu', function (Blueprint $table) {
            if (Schema::hasColumn('bahan_terima_gudang_satu', 'id_serah_terima_gudang_satu')) {
                $table->dropForeign(['id_serah_terima_gudang_satu']);
                $table->dropColumn('id_serah_terima_gudang_satu');
            }
        });
    }
};
