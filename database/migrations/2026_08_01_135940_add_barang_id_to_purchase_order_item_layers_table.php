<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_item_layers', function (Blueprint $table) {
            $table->foreignId('id_barang_setengah_jadi_hp')
                ->nullable()
                ->after('purchase_order_item_id')
                ->constrained('barang_setengah_jadi_hp')
                ->nullOnDelete();

            // material jadi boleh kosong dulu, nanti diisi otomatis dari nama barang veneer yang dipilih
            $table->string('material')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_item_layers', function (Blueprint $table) {
            $table->dropForeign(['id_barang_setengah_jadi_hp']);
            $table->dropColumn('id_barang_setengah_jadi_hp');
            $table->string('material')->nullable(false)->change();
        });
    }
};