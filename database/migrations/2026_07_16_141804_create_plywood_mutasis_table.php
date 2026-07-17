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
        Schema::create('plywood_mutasis', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal');
            $t->enum('tipe_transaksi', ['masuk', 'keluar']);
            $t->string('no_nota')->nullable();
            $t->string('tujuan_nota')->nullable();
            $t->string('status')->default('draft');
            $t->text('keterangan')->nullable();
            $t->foreignId('id_nota_bk')->nullable()->constrained('nota_barang_keluar')->nullOnDelete();
            $t->foreignId('id_nota_bm')->nullable()->constrained('nota_barang_masuks')->nullOnDelete();
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plywood_mutasis');
    }
};
