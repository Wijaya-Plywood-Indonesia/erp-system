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
        Schema::create('grade_rules', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel grades yang sudah ada
            $table->foreignId('id_grade')
                ->constrained('grades')
                ->cascadeOnDelete();

            // Relasi ke tabel criteria
            $table->foreignId('id_criteria')
                ->constrained('criterias')
                ->cascadeOnDelete();

            // Tipe kondisi: not_allowed | conditional | allowed
            $table->enum('kondisi', ['not_allowed', 'conditional', 'allowed'])
                ->default('not_allowed');

            // Penjelasan aturan ini (ditampilkan di hasil sebagai "alasan")
            // Contoh: "BBCC: Maks lebar 3mm, panjang 250mm, 2 titik"
            $table->text('penjelasan')->nullable();

            // Poin jika LULUS (tidak ada cacat, atau cacat tapi 'allowed')
            $table->decimal('poin_lulus', 5, 2)->default(100.00);

            // Poin jika PARSIAL (ada cacat tapi kondisi 'conditional')
            $table->decimal('poin_parsial', 5, 2)->default(0.00);

            $table->timestamps();

            // Satu grade hanya boleh punya satu aturan per kriteria
            $table->unique(['id_grade', 'id_criteria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grade_rules');
    }
};
