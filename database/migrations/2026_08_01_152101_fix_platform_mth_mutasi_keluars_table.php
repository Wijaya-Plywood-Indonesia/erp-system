<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_mth_mutasi_keluars', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'id_jenis_kayu')) {
                $table->foreignId('id_jenis_kayu')
                    ->after('id')
                    ->constrained('jenis_kayus')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'panjang')) {
                $table->decimal('panjang', 10, 2)->after('id_jenis_kayu');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'lebar')) {
                $table->decimal('lebar', 10, 2)->after('panjang');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'tebal')) {
                $table->decimal('tebal', 10, 2)->after('lebar');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'kw_grade')) {
                $table->string('kw_grade')->nullable()->after('tebal');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'stok_lembar')) {
                $table->unsignedInteger('stok_lembar')->after('kw_grade');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'stok_kubikasi')) {
                $table->decimal('stok_kubikasi', 14, 6)->default(0)->after('stok_lembar');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'tujuan')) {
                $table->string('tujuan')->default('Sanding')->after('stok_kubikasi');
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'dikeluarkan_by')) {
                $table->foreignId('dikeluarkan_by')
                    ->nullable()
                    ->after('tujuan')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('platform_mth_mutasi_keluars', 'keterangan')) {
                $table->text('keterangan')->nullable()->after('dikeluarkan_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_mth_mutasi_keluars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_jenis_kayu');
            $table->dropConstrainedForeignId('dikeluarkan_by');
            $table->dropColumn(['panjang', 'lebar', 'tebal', 'kw_grade', 'stok_lembar', 'stok_kubikasi', 'tujuan', 'keterangan']);
        });
    }
};
