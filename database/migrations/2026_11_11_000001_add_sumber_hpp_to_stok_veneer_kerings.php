<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            if (! Schema::hasColumn('stok_veneer_kerings', 'id_detail_bongkar_kedi')) {
                $table->unsignedBigInteger('id_detail_bongkar_kedi')->nullable()->index();
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'ongkos_pegawai_per_m3')) {
                $table->decimal('ongkos_pegawai_per_m3', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'ongkos_mesin_per_m3')) {
                $table->decimal('ongkos_mesin_per_m3', 18, 4)->default(0);
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'sumber_hpp_basah')) {
                $table->string('sumber_hpp_basah', 500)->nullable();
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'sumber_pegawai')) {
                $table->string('sumber_pegawai', 500)->nullable();
            }
            if (! Schema::hasColumn('stok_veneer_kerings', 'sumber_mesin')) {
                $table->string('sumber_mesin', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            foreach (['id_detail_bongkar_kedi', 'ongkos_pegawai_per_m3', 'ongkos_mesin_per_m3', 'sumber_hpp_basah', 'sumber_pegawai', 'sumber_mesin'] as $kolom) {
                if (Schema::hasColumn('stok_veneer_kerings', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
