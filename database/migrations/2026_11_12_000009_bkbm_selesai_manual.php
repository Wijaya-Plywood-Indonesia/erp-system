<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bk', 'selesai_manual_at')) {
            return;
        }
        Schema::table('bk', function (Blueprint $t) {
            $t->unsignedBigInteger('selesai_manual_oleh')->nullable();
            $t->timestamp('selesai_manual_at')->nullable();
            $t->text('selesai_manual_alasan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bk', fn (Blueprint $t) => $t->dropColumn(['selesai_manual_oleh', 'selesai_manual_at', 'selesai_manual_alasan']));
    }
};
