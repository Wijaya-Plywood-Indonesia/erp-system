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
        Schema::create('akun_groups', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); // Nama grup (Aktiva Lancar, Aktiva Tetap, dll)

            // Grup bisa punya parent (opsional)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('akun_groups')
                ->nullOnDelete();

            // Urutan tampil di laporan
            $table->integer('order')->default(0);

            $table->boolean('hidden')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akun_groups');
    }
};
