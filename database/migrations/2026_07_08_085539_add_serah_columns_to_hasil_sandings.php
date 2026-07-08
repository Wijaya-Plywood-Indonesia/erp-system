<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_sandings', function (Blueprint $table) {
            // Arah serah hasil sanding. NULL = belum diserahkan (belum antre di gudang mana pun).
            $table->string('tujuan_serah', 30)->nullable()->after('status')
                ->comment('platform_jadi | triplek_jadi');
            $table->foreignId('diserahkan_oleh')->nullable()->after('tujuan_serah')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('diserahkan_at')->nullable()->after('diserahkan_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('hasil_sandings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diserahkan_oleh');
            $table->dropColumn(['tujuan_serah', 'diserahkan_at']);
        });
    }
};