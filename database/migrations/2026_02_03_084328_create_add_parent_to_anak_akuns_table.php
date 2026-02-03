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
        Schema::table('anak_akuns', function (Blueprint $table) {

            // kolom parent (jika maksudnya refer ke anak_akuns juga -> self relation)
            $table->foreignId('parent')
                ->nullable()
                ->constrained('anak_akuns')
                ->nullOnDelete();

            // kolom created_by (biasanya refer ke users)
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status')->default('aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anak_akuns', function (Blueprint $table) {
            $table->dropForeign(['parent']);
            $table->dropColumn('parent');

            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by']);
        });
    }
};
