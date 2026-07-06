<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masuk_graji_triplek', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_hp')
                ->nullable()
                ->after('id_produksi_graji_triplek')
                ->constrained('serah_terima_hp')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('masuk_graji_triplek', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_serah_terima_hp');
        });
    }
};
