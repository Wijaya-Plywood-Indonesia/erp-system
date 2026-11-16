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
        Schema::table('penjualans', function (Blueprint $table) {
            $table->boolean('pakai_ppn')->default(true)->after('sub_total');
            $table->string('no_invoice_resmi')->nullable()->after('ppn_nominal');
            $table->string('pkp_nama')->nullable()->after('no_invoice_resmi');
            $table->string('pkp_alamat', 500)->nullable()->after('pkp_nama');
            $table->string('pkp_npwp')->nullable()->after('pkp_alamat');
            $table->string('pembeli_bkp_nama')->nullable()->after('pkp_npwp');
            $table->string('pembeli_bkp_alamat', 500)->nullable()->after('pembeli_bkp_nama');
            $table->string('pembeli_bkp_npwp')->nullable()->after('pembeli_bkp_alamat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn([
                'pakai_ppn',
                'no_invoice_resmi',
                'pkp_nama',
                'pkp_alamat',
                'pkp_npwp',
                'pembeli_bkp_nama',
                'pembeli_bkp_alamat',
                'pembeli_bkp_npwp',
            ]);
        });
    }
};
