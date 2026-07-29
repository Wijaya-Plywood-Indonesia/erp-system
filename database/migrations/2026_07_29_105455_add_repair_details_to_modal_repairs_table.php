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
        Schema::table('modal_repairs', function (Blueprint $table) {
            $table->enum('sumber', ['veneer_jadi', 'veneer_kering'])->after('id');
            $table->timestamp('ditutup_manual_at')->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users');
            $table->string('catatan_penutupan')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modal_repairs', function (Blueprint $table) {
            $table->dropForeign(['ditutup_oleh']);
            $table->dropColumn([
                'sumber',
                'ditutup_manual_at',
                'ditutup_oleh',
                'catatan_penutupan',
            ]);
        });
    }
};
