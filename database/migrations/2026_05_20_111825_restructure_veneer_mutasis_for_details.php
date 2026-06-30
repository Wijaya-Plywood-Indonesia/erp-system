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
        // 1. Create veneer_mutasi_details table
        Schema::create('veneer_mutasi_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_veneer_mutasi')->constrained('veneer_mutasis')->cascadeOnDelete();
            $table->enum('tipe_veneer', ['basah', 'kering']);
            $table->foreignId('id_ukuran')->constrained('ukurans')->cascadeOnDelete();
            $table->foreignId('id_jenis_kayu')->constrained('jenis_kayus')->cascadeOnDelete();
            $table->string('kw');
            $table->integer('qty');
            $table->decimal('m3', 10, 6);
            $table->timestamps();
        });

        // 2. Add status and drop old single-item columns from veneer_mutasis
        Schema::table('veneer_mutasis', function (Blueprint $table) {
            $table->dropForeign(['id_ukuran']);
            $table->dropForeign(['id_jenis_kayu']);
            $table->dropColumn(['tipe_veneer', 'id_ukuran', 'id_jenis_kayu', 'kw', 'qty', 'm3']);
            $table->string('status')->default('draft')->after('tujuan_nota');
        });

        // 3. Add id_veneer_mutasi_detail to stok_veneer_kerings
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->foreignId('id_veneer_mutasi_detail')->nullable()->after('id_veneer_mutasi')->constrained('veneer_mutasi_details')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_veneer_kerings', function (Blueprint $table) {
            $table->dropForeign(['id_veneer_mutasi_detail']);
            $table->dropColumn('id_veneer_mutasi_detail');
        });

        Schema::dropIfExists('veneer_mutasi_details');

        Schema::table('veneer_mutasis', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('tipe_veneer', ['basah', 'kering'])->nullable();
            $table->foreignId('id_ukuran')->nullable()->constrained('ukurans')->cascadeOnDelete();
            $table->foreignId('id_jenis_kayu')->nullable()->constrained('jenis_kayus')->cascadeOnDelete();
            $table->string('kw')->nullable();
            $table->integer('qty')->nullable();
            $table->decimal('m3', 10, 6)->nullable();
        });
    }
};
