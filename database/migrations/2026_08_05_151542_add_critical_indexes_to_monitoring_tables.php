<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kayu_masuks', function (Blueprint $table) {
            $table->index('updated_at');
            $table->index('id_supplier_kayus');
        });

        Schema::table('nota_kayus', function (Blueprint $table) {
            $table->index('id_kayu_masuk');
        });

        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->index('id_kayu_masuk');
        });

        Schema::table('detail_kayu_masuks', function (Blueprint $table) {
            $table->index('id_kayu_masuk');
        });
    }

    public function down(): void
    {
        Schema::table('kayu_masuks', function (Blueprint $table) {
            $table->dropIndex(['updated_at']);
            $table->dropIndex(['id_supplier_kayus']);
        });

        Schema::table('nota_kayus', function (Blueprint $table) {
            $table->dropIndex(['id_kayu_masuk']);
        });

        Schema::table('detail_turusan_kayus', function (Blueprint $table) {
            $table->dropIndex(['id_kayu_masuk']);
        });

        Schema::table('detail_kayu_masuks', function (Blueprint $table) {
            $table->dropIndex(['id_kayu_masuk']);
        });
    }
};
