<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kotak keluar: pesan ke pabrik lain disimpan dulu (satu transaksi dengan validasi), baru dikirim.
        Schema::create('bkbm_outbox', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();            // kunci idempotensi di sisi penerima
            $t->uuid('siklus_uuid')->index();
            $t->string('jenis', 30);               // buat_bm | status_update
            $t->json('payload');
            $t->string('status', 15)->default('pending'); // pending | terkirim | gagal
            $t->unsignedSmallInteger('percobaan')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('kirim_berikutnya_at')->nullable();
            $t->timestamp('terkirim_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'kirim_berikutnya_at']);
        });

        // Kotak masuk: catatan pesan yang sudah diproses (agar pesan ganda tidak diproses dua kali).
        Schema::create('bkbm_inbox', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('jenis', 30);
            $t->json('payload');
            $t->timestamp('diproses_at')->nullable();
            $t->timestamps();
        });

        Schema::table('bm', function (Blueprint $t) {
            if (! Schema::hasColumn('bm', 'ref_no_bk')) {
                $t->string('ref_no_bk')->nullable()->index();   // nomor BK di pabrik lain
                $t->string('ref_pabrik', 50)->nullable();
                $t->json('ref_snapshot')->nullable();           // salinan isi BK asal (hanya untuk dibaca)
            }
        });
    }

    public function down(): void
    {
        Schema::table('bm', fn (Blueprint $t) => $t->dropColumn(['ref_no_bk', 'ref_pabrik', 'ref_snapshot']));
        Schema::dropIfExists('bkbm_inbox');
        Schema::dropIfExists('bkbm_outbox');
    }
};
