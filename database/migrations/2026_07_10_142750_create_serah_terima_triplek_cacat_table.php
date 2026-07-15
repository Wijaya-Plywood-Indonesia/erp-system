<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serah_terima_triplek_cacat', function (Blueprint $table) {
            $table->id();

            $table->foreignId('id_hasil_pilih_plywood')
                ->constrained('hasil_pilih_plywood')
                ->cascadeOnDelete();

            $table->foreignId('id_produksi_dempul')
                ->nullable()
                ->constrained('produksi_dempuls')
                ->nullOnDelete();

            $table->foreignId('id_produksi_tembel_triplek')
                ->nullable()
                ->constrained('produksi_tembel_triplek')
                ->nullOnDelete();

            $table->enum('tujuan', ['dempul', 'tembel_triplek']);

            $table->string('diserahkan_oleh')->nullable();
            $table->string('diterima_oleh')->default('-');
            $table->string('status')->nullable();

            $table->timestamps();
        });

        Schema::table('bahan_dempuls', function (Blueprint $table) {
            $table->foreignId('id_serah_terima_triplek_cacat')
                ->nullable()
                ->constrained('serah_terima_triplek_cacat')
                ->nullOnDelete();
        });

        Schema::table('bahan_penolong_tembel_triplek', function (Blueprint $table) {
            $table->unsignedBigInteger('id_serah_terima_triplek_cacat')->nullable();
            $table->foreign('id_serah_terima_triplek_cacat', 'bp_tembel_cacat_fk')
                ->references('id')
                ->on('serah_terima_triplek_cacat')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bahan_dempuls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_serah_terima_triplek_cacat');
        });

        Schema::table('bahan_penolong_tembel_triplek', function (Blueprint $table) {
            $table->dropForeign('bp_tembel_cacat_fk');
            $table->dropColumn('id_serah_terima_triplek_cacat');
        });

        Schema::dropIfExists('serah_terima_triplek_cacat');
    }
};
