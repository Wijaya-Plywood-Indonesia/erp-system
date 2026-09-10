<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Daftar tabel area Finishing & Gudang
     */
    protected array $tables = [
        'produksi_graji_balken',
        'produksi_guellotine',
        'produksi_pilih_veneer',
        'produksi_sandings',
        'produksi_tembel_triplek',
        'produksi_dempuls',
        'produksi_graji_triplek',
        'produksi_nyusup',
        'produksi_pilih_plywood',
        'produksi_terima_gudang_satu',
        'detail_lain_lains',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                // 1. Tambah kolom uuid jika belum ada
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->uuid('uuid')->nullable()->after('id');
                    });
                }

                // 2. Isi UUID untuk data lama yang sudah ada di database
                $records = DB::table($tableName)->whereNull('uuid')->orWhere('uuid', '')->get();
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
