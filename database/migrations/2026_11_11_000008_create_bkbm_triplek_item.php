<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bkbm_triplek_item')) {
            return;
        }
        Schema::create('bkbm_triplek_item', function (Blueprint $t) {
            $t->id();
            $t->string('jenis', 10); // 'jadi' atau 'mth'
            $t->unsignedBigInteger('id_ukuran');
            $t->unsignedBigInteger('id_jenis_kayu');
            $t->string('kw_grade', 50);
            $t->unsignedInteger('qty');
            $t->decimal('m3', 14, 4)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bkbm_triplek_item');
    }
};
