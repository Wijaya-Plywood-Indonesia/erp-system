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
        Schema::table('produksi_hp', function (Blueprint $table) {
            $table->enum('shift', ['pagi', 'malam'])
                  ->after('tanggal_produksi');

            // ✅ cegah duplicate tanggal + shift
            $table->unique(['tanggal_produksi', 'shift']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produksi_hp', function (Blueprint $table) {
            $table->dropUnique(['tanggal_produksi', 'shift']);
            $table->dropColumn('shift');
        });
    }
};
