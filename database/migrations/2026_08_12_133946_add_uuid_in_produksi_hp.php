<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('produksi_hp') && !Schema::hasColumn('produksi_hp', 'uuid')) {
            // 1. Tambah kolom uuid (nullable dulu)
            Schema::table('produksi_hp', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });

            // 2. Isi UUID untuk data lama yang sudah ada
            $records = DB::table('produksi_hp')->whereNull('uuid')->get();
            foreach ($records as $record) {
                DB::table('produksi_hp')
                    ->where('id', $record->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            }

            // 3. Ubah kolom uuid menjadi NOT NULL & UNIQUE
            Schema::table('produksi_hp', function (Blueprint $table) {
                $table->uuid('uuid')->nullable(false)->unique()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produksi_hp') && Schema::hasColumn('produksi_hp', 'uuid')) {
            Schema::table('produksi_hp', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }
    }
};
