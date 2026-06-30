q<?php

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
            Schema::create('hasil_pilih_plywood_pegawai', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_hasil_pilih_plywood')
                    ->constrained('hasil_pilih_plywood')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->foreignId('id_pegawai')
                    ->constrained('pegawais')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();
                $table->timestamps();
            });
        }

        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('hasil_pilih_plywood_pegawai');
        }
    };
