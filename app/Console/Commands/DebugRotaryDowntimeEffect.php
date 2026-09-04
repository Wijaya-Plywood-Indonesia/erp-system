<?php

namespace App\Console\Commands;

use App\Filament\Pages\LaporanProduksi\Queries\LoadProduksi;
use App\Filament\Pages\LaporanProduksi\Transformers\ProduksiDataMap;
use Illuminate\Console\Command;

class DebugRotaryDowntimeEffect extends Command
{
    /**
     * php artisan debug:rotary-downtime-effect --tanggal=2026-08-20 --id_mesin=4
     * php artisan debug:rotary-downtime-effect --tanggal=2026-08-20        (semua mesin)
     */
    protected $signature = 'debug:rotary-downtime-effect
        {--tanggal= : Tanggal produksi (Y-m-d)}
        {--id_mesin= : Filter ke satu id_mesin saja (opsional, kosongkan untuk semua mesin rotary di tanggal itu)}';

    protected $description = 'Bandingkan potongan Rotary ASLI (dengan downtime) vs SIMULASI (downtime dianggap 0) untuk data 1 hari, memakai ProduksiDataMap yang sama persis dengan laporan.';

    public function handle(): int
    {
        $tanggal = $this->option('tanggal');
        $idMesin = $this->option('id_mesin');

        if (! $tanggal) {
            $this->error('Wajib isi --tanggal=Y-m-d');

            return self::FAILURE;
        }

        $raw = LoadProduksi::run($tanggal);

        if (! $raw || $raw->isEmpty()) {
            $this->warn("Tidak ada data produksi rotary untuk tanggal {$tanggal}.");

            return self::SUCCESS;
        }

        if ($idMesin) {
            $raw = $raw->filter(fn ($item) => (int) $item->id_mesin === (int) $idMesin)->values();

            if ($raw->isEmpty()) {
                $this->warn("Tidak ada data produksi untuk id_mesin={$idMesin} di tanggal {$tanggal}.");

                return self::SUCCESS;
            }
        }

        $this->info("Ditemukan {$raw->count()} baris produksi rotary untuk tanggal {$tanggal}".($idMesin ? " (id_mesin={$idMesin})" : ' (semua mesin)').'.');
        $this->newLine();

        // ---------------------------------------------------------
        // 1. Hasil ASLI — pakai data apa adanya (downtime real dari
        //    kendalaRotaries), persis seperti yang tampil di laporan.
        // ---------------------------------------------------------
        $withDowntime = ProduksiDataMap::make($raw);

        // ---------------------------------------------------------
        // 2. Hasil SIMULASI — clone tiap item, kosongkan relasi
        //    kendalaRotaries (downtime dipaksa = 0), lalu jalankan
        //    ProduksiDataMap yang SAMA. Ini membuktikan efeknya
        //    langsung dari kode produksi, bukan rumus manual terpisah.
        // ---------------------------------------------------------
        $noDowntime = $raw->map(function ($item) {
            $clone = clone $item;
            $clone->setRelation('kendalaRotaries', collect());

            return $clone;
        });

        $withoutDowntime = ProduksiDataMap::make($noDowntime);

        // ---------------------------------------------------------
        // 3. Tabel perbandingan, baris per baris (index sejajar
        //    karena urutan collection sama).
        // ---------------------------------------------------------
        $rows = [];
        foreach ($withDowntime as $i => $asli) {
            $simulasi = $withoutDowntime[$i] ?? null;

            $rows[] = [
                'mesin' => $asli['mesin'],
                'downtime (menit)' => $asli['total_downtime_menit'],
                'jam_kerja_efektif ASLI' => $asli['jam_kerja_efektif'],
                'jam_kerja_efektif TANPA downtime' => $simulasi['jam_kerja_efektif'] ?? '-',
                'target_adjusted ASLI' => $asli['target'],
                'target_adjusted TANPA downtime' => $simulasi['target'] ?? '-',
                'potongan_total ASLI' => $asli['potongan_total'],
                'potongan_total TANPA downtime' => $simulasi['potongan_total'] ?? '-',
                'selisih potongan (asli - tanpa dt)' => $asli['potongan_total'] - ($simulasi['potongan_total'] ?? 0),
            ];
        }

        $this->table(array_keys($rows[0]), $rows);

        $this->newLine();
        $totalSelisih = array_sum(array_column($rows, 'selisih potongan (asli - tanpa dt)'));

        if ($totalSelisih == 0) {
            $this->warn('Selisih total = 0 -> untuk data tanggal ini, downtime TIDAK mengubah potongan (kemungkinan karena hasil sudah di atas target meski downtime dihilangkan, atau memang tidak ada downtime di hari ini).');
        } else {
            $this->info("Selisih total potongan (ASLI - TANPA downtime) = {$totalSelisih}. Angka != 0 ini MEMBUKTIKAN downtime berpengaruh langsung ke potongan pada data tanggal {$tanggal}.");
        }

        return self::SUCCESS;
    }
}
