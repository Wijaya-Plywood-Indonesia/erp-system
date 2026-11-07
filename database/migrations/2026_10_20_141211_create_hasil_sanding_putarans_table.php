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
        Schema::create('hasil_sanding_putarans', function (Blueprint $table) {
            $t = $table;
            $t->id();
            // Tanpa constrained(): saya tidak tahu nama pasti tabel HasilSanding-mu
            $t->unsignedBigInteger('hasil_sanding_id')->index();
            $t->string('sisi', 4);                 // 'face' / 'back'
            $t->unsignedTinyInteger('putaran_ke'); // 1 atau 2
            $t->unsignedInteger('modal');
            $t->unsignedInteger('hasil');
            $t->integer('selisih');
            $t->string('status_cek', 20);          // sesuai / tidak_sesuai
            $t->string('catatan', 500)->nullable();
            $t->string('machine_id', 100)->nullable();
            $t->dateTime('waktu_mesin')->nullable();
            $t->timestamps();

            // Mencegah putaran yang sama tercatat dua kali
            $t->unique(['hasil_sanding_id', 'sisi', 'putaran_ke']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_sanding_putarans');
    }
};
