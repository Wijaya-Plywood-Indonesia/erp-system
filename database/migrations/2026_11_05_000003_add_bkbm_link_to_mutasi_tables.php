<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tabel = ['plywood_mutasis', 'platform_jadi_mutasis', 'platform_mth_mutasis'];

    public function up(): void
    {
        foreach ($this->tabel as $nama) {
            Schema::table($nama, function (Blueprint $t) use ($nama) {
                if (! Schema::hasColumn($nama, 'id_bk')) {
                    $t->unsignedBigInteger('id_bk')->nullable()->index();
                    $t->unsignedBigInteger('id_bm')->nullable()->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabel as $nama) {
            Schema::table($nama, fn (Blueprint $t) => $t->dropColumn(['id_bk', 'id_bm']));
        }
    }
};
