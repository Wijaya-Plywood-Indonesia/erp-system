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
        Schema::create('platform_mth_mutasi_details', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_platform_mth_mutasi')->constrained('platform_mth_mutasis')->cascadeOnDelete();
            $t->foreignId('id_ukuran')->constrained('ukurans');
            // stok_platform_mth masih memakai id_jenis_kayu (tidak seperti stok_platform_jadi).
            $t->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
            $t->string('kw_grade');
            $t->integer('qty');
            $t->decimal('m3', 12, 6);
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_mth_mutasi_details');
    }
};
