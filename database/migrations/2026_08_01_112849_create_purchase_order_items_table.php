<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap item PO = 1 pesanan Plywood (barang setengah jadi).
     * Karena barang yang dipesan sudah pasti Plywood dan komposisinya
     * sudah pasti susunan Veneer, tidak perlu tabel master "barangs" lagi.
     */
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('jumlah'); // jumlah lembar plywood
            $table->text('keterangan')->nullable();
            $table->boolean('status')->default(false); // selesai / belum
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};