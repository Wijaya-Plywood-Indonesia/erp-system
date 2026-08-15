<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SyncEmptyUuidCommand extends Command
{
    /**
     * Nama perintah yang akan diketik di terminal.
     */
    protected $signature = 'app:sync-uuid';

    /**
     * Deskripsi perintah.
     */
    protected $description = 'Mengisi kolom UUID yang bernilai NULL atau kosong pada semua tabel produksi dan kayu.';

    /**
     * Daftar seluruh tabel target.
     */
    protected array $tables = [
        // Sektor Rotary & Dryer
        'produksi_rotaries',
        'produksi_press_dryers',
        'produksi_kedi',
        'produksi_stik',
        'graji_stiks',

        // Sektor Repair, Joint & Hotpress
        'produksi_repairs',
        'produksi_joint',
        'produksi_pot_af_joint',
        'produksi_sanding_joint',
        'produksi_hp',

        // Sektor Finishing & Gudang
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

        // Sektor Logistik & Kayu
        'nota_kayus',
        'kayu_masuks',
    ];

    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  Memulai Sinkronisasi UUID pada Database...        ');
        $this->info('====================================================');

        $totalUpdated = 0;

        foreach ($this->tables as $tableName) {
            // 1. Validasi keberadaan tabel & kolom uuid
            if (!Schema::hasTable($tableName)) {
                $this->warn("[-] Tabel '{$tableName}' tidak ditemukan di database. Dilewati.");
                continue;
            }

            if (!Schema::hasColumn($tableName, 'uuid')) {
                $this->warn("[-] Kolom 'uuid' pada tabel '{$tableName}' tidak ditemukan. Dilewati.");
                continue;
            }

            // 2. Ambil data yang UUID-nya masih NULL atau kosong string ''
            $emptyRecords = DB::table($tableName)
                ->whereNull('uuid')
                ->orWhere('uuid', '')
                ->get(['id']);

            $count = $emptyRecords->count();

            if ($count > 0) {
                // 3. Update satu per satu dengan UUID unik
                foreach ($emptyRecords as $record) {
                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }

                $this->info("[✓] Tabel '{$tableName}': {$count} data berhasil diisi UUID.");
                $totalUpdated += $count;
            } else {
                $this->line("[•] Tabel '{$tableName}': Sudah lengkap (0 data kosong).");
            }
        }

        $this->newLine();
        $this->info("====================================================");
        $this->info("  Selesai! Total record yang diperbarui: {$totalUpdated}");
        $this->info("====================================================");

        return Command::SUCCESS;
    }
}
