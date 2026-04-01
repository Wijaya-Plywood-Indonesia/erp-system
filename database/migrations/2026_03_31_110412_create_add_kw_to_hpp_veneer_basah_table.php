<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah kolom kw di logs (jika belum ada)
        if (!Schema::hasColumn('hpp_veneer_basah_logs', 'kw')) {
            Schema::table('hpp_veneer_basah_logs', function (Blueprint $table) {
                $table->string('kw', 10)->nullable()->after('tebal');
            });
        }

        // Tambah kolom kw di summaries (jika belum ada)
        if (!Schema::hasColumn('hpp_veneer_basah_summaries', 'kw')) {
            Schema::table('hpp_veneer_basah_summaries', function (Blueprint $table) {
                $table->string('kw', 10)->nullable()->after('tebal');
            });
        }

        // Drop semua unique index lama, buat baru dengan kw
        $indexes = collect(DB::select("SHOW INDEX FROM hpp_veneer_basah_summaries WHERE Key_name != 'PRIMARY'"))
            ->pluck('Key_name')->unique();

        foreach ($indexes as $indexName) {
            try {
                DB::statement("ALTER TABLE hpp_veneer_basah_summaries DROP INDEX `{$indexName}`");
            } catch (\Throwable $e) {
                // abaikan
            }
        }

        // Tambah unique baru dengan kw (jika belum ada)
        $hasUnique = collect(DB::select("SHOW INDEX FROM hpp_veneer_basah_summaries WHERE Key_name = 'hpp_veneer_basah_summaries_kombinasi_unique'"))->isNotEmpty();
        if (!$hasUnique) {
            Schema::table('hpp_veneer_basah_summaries', function (Blueprint $table) {
                $table->unique(['id_jenis_kayu', 'panjang', 'lebar', 'tebal', 'kw'], 'hpp_veneer_basah_summaries_kombinasi_unique');
            });
        }

        // Tambah kolom kw di bahan penolong (jika belum ada)
        if (!Schema::hasColumn('hpp_veneer_basah_bahan_penolong', 'kw')) {
            Schema::table('hpp_veneer_basah_bahan_penolong', function (Blueprint $table) {
                $table->string('kw', 10)->nullable()->after('id_log');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hpp_veneer_basah_logs', function (Blueprint $table) {
            $table->dropColumn('kw');
        });

        Schema::table('hpp_veneer_basah_summaries', function (Blueprint $table) {
            try { $table->dropUnique('hpp_veneer_basah_summaries_kombinasi_unique'); } catch (\Throwable $e) {}
            $table->dropColumn('kw');
        });

        Schema::table('hpp_veneer_basah_bahan_penolong', function (Blueprint $table) {
            $table->dropColumn('kw');
        });
    }
};