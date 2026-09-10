<?php

namespace App\Console\Commands;

use App\Filament\Pages\LaporanProduksi\Queries\LoadProduksi;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DebugRotaryKendala extends Command
{
    /**
     * php artisan debug:rotary-kendala --tanggal=2026-08-20 --id_mesin=4
     */
    protected $signature = 'debug:rotary-kendala
        {--tanggal= : Tanggal produksi (Y-m-d)}
        {--id_mesin= : Filter ke satu id_mesin saja (opsional)}';

    protected $description = 'Breakdown detail kendala rotary per hari: bandingkan durasi_menit (kolom tersimpan) vs selisih timestamp aktual, dan lihat proses merge interval yang menghasilkan total_downtime_menit.';

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
        }

        if ($raw->isEmpty()) {
            $this->warn('Tidak ada data produksi yang cocok dengan filter.');

            return self::SUCCESS;
        }

        foreach ($raw as $item) {
            $namaMesin = $item->mesin->nama_mesin ?? 'TIDAK ADA MESIN';

            $this->newLine();
            $this->line("<fg=cyan;options=bold>=== Produksi id={$item->id} | {$namaMesin} | {$tanggal} ===</>");

            if (empty($item->kendalaRotaries) || $item->kendalaRotaries->count() === 0) {
                $this->comment('Tidak ada kendala untuk produksi ini.');

                continue;
            }

            // ---------------------------------------------------------
            // 1. RAW: semua row kendala, apa adanya dari DB
            // ---------------------------------------------------------
            $rawRows = [];
            $intervals = [];

            foreach ($item->kendalaRotaries as $knd) {
                $mulai = $knd->waktu_mulai ? Carbon::parse($knd->waktu_mulai) : null;
                $selesai = $knd->waktu_selesai ? Carbon::parse($knd->waktu_selesai) : null;

                $selisihDetik = ($mulai && $selesai) ? $selesai->diffInSeconds($mulai) : null;
                $selisihMenitExact = $selisihDetik !== null ? round($selisihDetik / 60, 2) : null;

                $dipakaiUntukTotal = ($knd->status === 'selesai' && ! is_null($knd->durasi_menit) && $mulai && $selesai && $selesai->timestamp > $mulai->timestamp);

                $rawRows[] = [
                    'id' => $knd->id,
                    'kendala' => $knd->kendala,
                    'status' => $knd->status,
                    'waktu_mulai (raw)' => $knd->waktu_mulai,
                    'waktu_selesai (raw)' => $knd->waktu_selesai,
                    'durasi_menit (kolom DB)' => $knd->durasi_menit ?? '-',
                    'selisih timestamp (detik)' => $selisihDetik ?? '-',
                    'selisih timestamp (menit, exact)' => $selisihMenitExact ?? '-',
                    'dipakai hitung total downtime?' => $dipakaiUntukTotal ? 'YA' : 'TIDAK',
                ];

                if ($dipakaiUntukTotal) {
                    $intervals[] = [
                        'start' => $mulai->timestamp,
                        'end' => $selesai->timestamp,
                        'label' => $knd->kendala.' ('.$mulai->format('H:i:s').'-'.$selesai->format('H:i:s').')',
                    ];
                }
            }

            $this->table(array_keys($rawRows[0]), $rawRows);

            // ---------------------------------------------------------
            // 2. Peringatan kalau durasi_menit (DB) != selisih timestamp
            // ---------------------------------------------------------
            foreach ($rawRows as $r) {
                if ($r['selisih timestamp (menit, exact)'] !== '-'
                    && (float) $r['durasi_menit (kolom DB)'] !== (float) $r['selisih timestamp (menit, exact)']) {
                    $this->warn("  ! Kendala id={$r['id']}: durasi_menit tersimpan ({$r['durasi_menit (kolom DB)']}) BEDA dengan selisih timestamp aktual ({$r['selisih timestamp (menit, exact)']}). total_downtime_menit pakai yang TIMESTAMP, bukan kolom durasi_menit.");
                }
            }

            if (empty($intervals)) {
                $this->comment('Tidak ada interval valid untuk dihitung ke total downtime.');

                continue;
            }

            // ---------------------------------------------------------
            // 3. Proses MERGE interval (union, biar overlap tidak dobel)
            // ---------------------------------------------------------
            $this->newLine();
            $this->line('<fg=yellow>--- Interval sebelum merge ---</>');
            usort($intervals, fn ($a, $b) => $a['start'] <=> $b['start']);
            foreach ($intervals as $iv) {
                $this->line('  '.$iv['label'].' -> '.round(($iv['end'] - $iv['start']) / 60, 2).' menit');
            }

            $merged = [];
            foreach ($intervals as $interval) {
                if (empty($merged)) {
                    $merged[] = $interval;
                } else {
                    $lastIndex = count($merged) - 1;
                    if ($interval['start'] <= $merged[$lastIndex]['end']) {
                        $merged[$lastIndex]['end'] = max($merged[$lastIndex]['end'], $interval['end']);
                        $merged[$lastIndex]['label'] .= ' + '.$interval['label'].' (OVERLAP, digabung)';
                    } else {
                        $merged[] = $interval;
                    }
                }
            }

            $this->newLine();
            $this->line('<fg=yellow>--- Interval SETELAH merge (ini yang dijumlah) ---</>');
            $totalSeconds = 0;
            foreach ($merged as $iv) {
                $durasiMenit = round(($iv['end'] - $iv['start']) / 60, 2);
                $totalSeconds += ($iv['end'] - $iv['start']);
                $this->line('  '.$iv['label'].' -> '.$durasiMenit.' menit');
            }

            $totalDowntimeMenit = (int) round($totalSeconds / 60);

            $this->newLine();
            $jumlahDurasiKolom = array_sum(array_map(
                fn ($r) => is_numeric($r['durasi_menit (kolom DB)']) ? (float) $r['durasi_menit (kolom DB)'] : 0,
                $rawRows
            ));

            $this->table(
                ['Metode', 'Hasil'],
                [
                    ['SUM durasi_menit (kolom DB, kalau overlap dijumlah dobel)', $jumlahDurasiKolom.' menit'],
                    ['total_downtime_menit (union timestamp, dipakai sistem)', $totalDowntimeMenit.' menit'],
                ]
            );

            if ((float) $jumlahDurasiKolom !== (float) $totalDowntimeMenit) {
                $this->warn('=> Ada selisih antara SUM durasi_menit vs total_downtime_menit aktual. Lihat tabel raw di atas + peringatan "BEDA" untuk cari row penyebabnya.');
            } else {
                $this->info('=> Cocok, tidak ada selisih.');
            }
        }

        return self::SUCCESS;
    }
}
