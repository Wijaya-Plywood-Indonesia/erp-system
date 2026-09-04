<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->string('ditolak_oleh')->nullable()->after('diterima_oleh');
            $table->text('alasan_tolak')->nullable()->after('ditolak_oleh');
            $table->timestamp('ditolak_at')->nullable()->after('alasan_tolak');
        });
    }

    public function down(): void
    {
        Schema::table('serah_terima_hp', function (Blueprint $table) {
            $table->dropColumn(['ditolak_oleh', 'alasan_tolak', 'ditolak_at']);
        });
    }
};