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
        Schema::create('session_answers', function (Blueprint $table) {
            $table->id();

            // Sesi mana yang sedang berjalan
            $table->foreignId('id_session')
                ->constrained('grading_sessions')
                ->cascadeOnDelete();

            // Pertanyaan / kriteria mana yang dijawab
            $table->foreignId('id_criteria')
                ->constrained('criterias')
                ->cascadeOnDelete();

            // Jawaban pengawas: 'ya' (ada cacat) atau 'tidak' (tidak ada)
            $table->enum('jawaban', ['ya', 'tidak']);

            // Kapan pertanyaan ini dijawab
            $table->timestamp('answered_at')->useCurrent();

            // Satu sesi tidak boleh menjawab kriteria yang sama dua kali
            $table->unique(['id_session', 'id_criteria']);

            $table->index('id_session');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_answers');
    }
};
