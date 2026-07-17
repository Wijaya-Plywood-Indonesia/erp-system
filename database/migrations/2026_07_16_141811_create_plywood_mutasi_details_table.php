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
    Schema::create('plywood_mutasi_details', function (Blueprint $t) {
        $t->id();
        $t->foreignId('id_plywood_mutasi')->constrained('plywood_mutasis')->cascadeOnDelete();
        $t->foreignId('id_ukuran')->constrained('ukurans');
        $t->foreignId('id_jenis_kayu')->constrained('jenis_kayus');
        $t->string('kw_grade');
        $t->integer('qty');
        $t->decimal('m3', 12, 6);
        $t->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('plywood_mutasi_details');
}
};
