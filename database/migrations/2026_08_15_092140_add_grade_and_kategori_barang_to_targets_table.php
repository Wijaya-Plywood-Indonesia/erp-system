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
        Schema::table('targets', function (Blueprint $table) {
            $table->foreignId('id_kategori_barang')
                ->nullable()
                ->after('id_jenis_kayu')
                ->constrained('kategori_barang')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // 2. Kolom untuk grade barang/kayu
            $table->string('grade')
                ->nullable()
                ->after('id_kategori_barang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('targets', function (Blueprint $table) {
            $table->dropForeign(['id_kategori_barang']);
            $table->dropColumn(['id_kategori_barang', 'grade']);
        });
    }
};
