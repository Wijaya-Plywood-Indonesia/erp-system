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
        Schema::create('kayu_masuks', function (Blueprint $table) {
            $table->id();

            $table->string('jenis_dokumen_angkut');
            $table->string('upload_dokumen_angkut');
            $table->dateTime('tgl_kayu_masuk');
            $table->integer('seri');

            // Relasi supplier, kendaraan, dokumen
            $table->foreignId('id_supplier_kayus')
                ->nullable()
                ->constrained('supplier_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_kendaraan_supplier_kayus')
                ->nullable()
                ->constrained('kendaraan_supplier_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_dokumen_kayus')
                ->nullable()
                ->constrained('dokumen_kayus')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Kolom tambahan created_by & updated_by
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kayu_masuks');
    }
};
