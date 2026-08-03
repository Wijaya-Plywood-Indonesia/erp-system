<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Susunan lapisan veneer untuk 1 item plywood.
     * "material" langsung berupa nama veneer (string), tidak perlu
     * relasi ke tabel master karena jenis material bisa bervariasi bebas.
     */
    public function up(): void
    {
        Schema::create('purchase_order_item_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('urutan')->default(1);
            $table->string('material'); // contoh: "Veneer Meranti 1mm (Face/Back)"
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_item_layers');
    }
};