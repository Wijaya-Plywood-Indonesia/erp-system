<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Daftar tabel nota dan kayu masuk
     */
    protected array $tables = [
        'nota_kayus',
        'kayu_masuks',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                // 1. Tambahkan kolom uuid jika belum ada
                if (!Schema::hasColumn($tableName, 'uuid')) {
                    Schema::table($tableName, function (Blueprint $table) {
                        $table->uuid('uuid')->nullable()->after('id');
                    });
                }

                // 2. Generate UUID untuk data lama yang masih kosong
                $records = DB::table($tableName)->whereNull('uuid')->orWhere('uuid', '')->get();
                foreach ($records as $record) {
                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }

                // 3. Ubah kolom uuid menjadi NOT NULL dan UNIQUE
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
