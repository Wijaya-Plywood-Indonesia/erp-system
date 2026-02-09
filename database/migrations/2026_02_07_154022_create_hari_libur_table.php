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
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // tidak unique agar bisa beda tahun
            $table->string('name');
            $table->enum('type', [
                'national',
                'cuti_bersama',
                'religion',
                'company',
                'custom'
            ])->default('national');

            // libur yang selalu sama tiap tahun (contoh: Natal, Tahun Baru)
            $table->boolean('is_repeat_yearly')->default(false);

            // sumber dari API/manual/auto_sync
            $table->string('source')->nullable();

            $table->timestamps();

            // index agar pencarian cepat
            $table->index(['date']);
            $table->index(['is_repeat_yearly']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};
