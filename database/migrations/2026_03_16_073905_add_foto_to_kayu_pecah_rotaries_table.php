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
        Schema::table('kayu_pecah_rotaries', function (Blueprint $table) {
            if (!Schema::hasColumn('kayu_pecah_rotaries', 'foto')) {
                $table->string('foto')->nullable()->after('ukuran');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kayu_pecah_rotaries', function (Blueprint $table) {
            if (Schema::hasColumn('kayu_pecah_rotaries', 'foto')) {
                $table->dropColumn('foto');
            }
        });
    }
};
