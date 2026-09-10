<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Daftar tabel produksi baru yang akan ditambahkan kolom UUID
     */
    protected array $tables = [
        'produksi_repairs',
        'produksi_joint',
        'produksi_pot_af_joint',
        'produksi_sanding_joint',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && !Schema::hasColumn($tableName, 'uuid')) {
                // 1. Tambah kolom uuid (nullable dulu)
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->after('id');
                });

                // 2. Generate UUID untuk data lama yang sudah ada di database
                $records = DB::table($tableName)->whereNull('uuid')->get();
                foreach ($records as $record) {
                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }

                // 3. Ubah kolom uuid menjadi NOT NULL & UNIQUE
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->unique()->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uuid');
                });
            }
        }
    }
};
