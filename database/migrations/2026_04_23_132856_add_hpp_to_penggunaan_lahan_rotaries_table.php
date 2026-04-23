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
        Schema::table('penggunaan_lahan_rotaries', function (Blueprint $table) {
            if (!Schema::hasColumn('penggunaan_lahan_rotaries', 'hpp_average')) {
                $table->decimal('hpp_average', 20, 2)->default(0)
                    ->after('jumlah_batang');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penggunaan_lahan_rotaries', function (Blueprint $table) {
            if (Schema::hasColumn('penggunaan_lahan_rotaries', 'hpp_average')) {
                $table->dropColumn('hpp_average');
            }
        });
    }
};
