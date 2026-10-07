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
        Schema::create('hasil_log_cores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produksi_rotary_id')
                ->constrained('produksi_rotaries')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Relasi ke jenis kayu (sesuaikan nama tabel jika berbeda, misal 'jenis_kayus')
            $table->foreignId('id_jenis_kayu')
                ->constrained('jenis_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->integer('panjang');
            $table->decimal('qty', 10, 2);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hasil_log_cores');
    }
};
