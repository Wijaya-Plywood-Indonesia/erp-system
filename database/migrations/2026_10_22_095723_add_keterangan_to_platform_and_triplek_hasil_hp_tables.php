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
        Schema::table('platform_hasil_hp', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_hasil_hp', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('isi');
            }
        });

        Schema::table('triplek_hasil_hp', function (Blueprint $table) {
            if (! Schema::hasColumn('triplek_hasil_hp', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('isi');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_hasil_hp', function (Blueprint $table) {
            if (Schema::hasColumn('platform_hasil_hp', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
        });

        Schema::table('triplek_hasil_hp', function (Blueprint $table) {
            if (Schema::hasColumn('triplek_hasil_hp', 'keterangan')) {
                $table->dropColumn('keterangan');
            }
        });
    }
};
