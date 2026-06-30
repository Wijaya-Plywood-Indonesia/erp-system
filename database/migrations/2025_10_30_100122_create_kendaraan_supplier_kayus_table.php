<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kendaraan_supplier_kayus', function (Blueprint $table) {
            $table->id();

            // Foreign key supplier
            $table->foreignId('id_supplier')
                ->nullable()
                ->constrained('supplier_kayus')
                ->nullOnDelete();

            $table->string('nopol_kendaraan');
            $table->string('jenis_kendaraan');
            $table->string('pemilik_kendaraan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kendaraan_supplier_kayus');
    }
};
