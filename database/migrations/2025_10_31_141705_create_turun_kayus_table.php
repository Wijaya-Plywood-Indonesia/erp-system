<?php
// File: ...database/migrations/..._create_turun_kayus_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('turun_kayus', function (Blueprint $table) {
            $table->id();
            $table->dateTime('tanggal'); // Sesuai Model
            $table->string('kendala')->nullable(); // Sesuai Model

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turun_kayus');
    }
};