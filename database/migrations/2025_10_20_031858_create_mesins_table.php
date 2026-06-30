<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mesins', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kategori_mesin_id')
                ->constrained('kategori_mesins') // relasi ke tabel kategori_mesins
                ->cascadeOnUpdate()
                ->restrictOnDelete(); // mencegah hapus kategori jika masih ada mesin
            $table->string('jenis_hasil')->nullable();
            $table->string('nama_mesin');
            $table->decimal('ongkos_mesin', 15, 2);
            $table->string('no_akun');
            $table->text('detail_mesin')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mesins');
    }
};
