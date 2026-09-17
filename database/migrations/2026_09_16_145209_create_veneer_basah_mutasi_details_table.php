<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('veneer_basah_mutasi_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_veneer_basah_mutasi')
                ->constrained('veneer_basah_mutasis')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('id_ukuran')
                ->constrained('ukurans')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('kw', 10);
            $table->integer('qty_lembar');
            $table->decimal('m3', 12, 6)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('veneer_basah_mutasi_details');
    }
};
