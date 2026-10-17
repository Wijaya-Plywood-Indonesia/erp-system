<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('serah_terima_veneer_kering')) {
            Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
                if (! Schema::hasColumn('serah_terima_veneer_kering', 'ditolak_oleh')) {
                    $table->string('ditolak_oleh')->nullable()->after('diterima_oleh');
                }
                if (! Schema::hasColumn('serah_terima_veneer_kering', 'alasan_tolak')) {
                    $table->text('alasan_tolak')->nullable()->after('ditolak_oleh');
                }
                if (! Schema::hasColumn('serah_terima_veneer_kering', 'ditolak_at')) {
                    $table->timestamp('ditolak_at')->nullable()->after('alasan_tolak');
                }
            });
        }

        if (Schema::hasTable('detail_hasil_palet_rotary_serah_terima_pivot')) {
            Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
                if (! Schema::hasColumn('detail_hasil_palet_rotary_serah_terima_pivot', 'ditolak_oleh')) {
                    $table->string('ditolak_oleh')->nullable()->after('diterima_oleh');
                }
                if (! Schema::hasColumn('detail_hasil_palet_rotary_serah_terima_pivot', 'alasan_tolak')) {
                    $table->text('alasan_tolak')->nullable()->after('ditolak_oleh');
                }
                if (! Schema::hasColumn('detail_hasil_palet_rotary_serah_terima_pivot', 'ditolak_at')) {
                    $table->timestamp('ditolak_at')->nullable()->after('alasan_tolak');
                }
            });
        }

        if (Schema::hasTable('hasil_pilih_veneer')) {
            Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
                if (! Schema::hasColumn('hasil_pilih_veneer', 'ditolak_gudang_oleh')) {
                    $table->string('ditolak_gudang_oleh')->nullable()->after('diterima_gudang_by');
                }
                if (! Schema::hasColumn('hasil_pilih_veneer', 'alasan_tolak_gudang')) {
                    $table->text('alasan_tolak_gudang')->nullable()->after('ditolak_gudang_oleh');
                }
                if (! Schema::hasColumn('hasil_pilih_veneer', 'ditolak_gudang_at')) {
                    $table->timestamp('ditolak_gudang_at')->nullable()->after('alasan_tolak_gudang');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('serah_terima_veneer_kering')) {
            Schema::table('serah_terima_veneer_kering', function (Blueprint $table) {
                $table->dropColumn(['ditolak_oleh', 'alasan_tolak', 'ditolak_at']);
            });
        }

        if (Schema::hasTable('detail_hasil_palet_rotary_serah_terima_pivot')) {
            Schema::table('detail_hasil_palet_rotary_serah_terima_pivot', function (Blueprint $table) {
                $table->dropColumn(['ditolak_oleh', 'alasan_tolak', 'ditolak_at']);
            });
        }

        if (Schema::hasTable('hasil_pilih_veneer')) {
            Schema::table('hasil_pilih_veneer', function (Blueprint $table) {
                $table->dropColumn(['ditolak_gudang_oleh', 'alasan_tolak_gudang', 'ditolak_gudang_at']);
            });
        }
    }
};
