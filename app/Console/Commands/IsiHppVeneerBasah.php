<?php

namespace App\Console\Commands;

use App\Models\DetailHasilPaletRotary;
use App\Models\HppVeneerBasahLog;
use App\Services\HppVeneerBasahIsiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IsiHppVeneerBasah extends Command
{
    /**
     * Contoh:
     *   php artisan hpp:veneer-isi                       → DRY-RUN (tidak menyimpan apa pun)
     *   php artisan hpp:veneer-isi --apply               → simpan (hanya log HPP kosong / berestimasi)
     *   php artisan hpp:veneer-isi --semua --apply       → timpa juga log yang sudah berHPP (rumus lama)
     *   php artisan hpp:veneer-isi --semua --sejak=2026-10-12 --apply   → hanya log sejak tanggal itu
     *
     * AMAN UNTUK SISTEM YANG SEDANG BERJALAN:
     *   - tiap kombinasi (jenis kayu + ukuran + KW) diproses dalam transaksi PENDEK sendiri,
     *     jadi penerimaan palet pada kombinasi lain tidak ikut menunggu
     *   - hasil dihitung dari sumbernya, sehingga boleh dijalankan berulang kali (idempoten)
     *   - kombinasi yang gagal dibatalkan sendiri dan dilaporkan, yang lain tetap lanjut
     */
    protected $signature = 'hpp:veneer-isi
                            {--apply : Simpan perubahan (tanpa ini hanya simulasi, semua dibatalkan)}
                            {--semua : Proses SEMUA log masuk palet, bukan hanya yang HPP-nya kosong / estimasi}
                            {--sejak= : Hanya log dengan tanggal >= YYYY-MM-DD}';

    protected $description = 'Isi HPP log masuk veneer basah dari batch lahan (rumus Persentase Kayu) lalu hitung ulang moving average.';

    public function handle(HppVeneerBasahIsiService $isi): int
    {
        $apply = (bool) $this->option('apply');

        $this->info($apply
            ? 'MODE: APPLY — perubahan DISIMPAN (per kombinasi).'
            : 'MODE: DRY-RUN — tiap kombinasi dikerjakan lalu dibatalkan.');
        $this->line('Saklar alur baru (HPP_VENEER_BASAH_BARU): '.(config('hpp.veneer_basah_baru') ? 'NYALA' : 'MATI'));

        if ($apply && ! $this->confirm('Sudah backup tabel hpp_veneer_basah_logs & hpp_veneer_basah_summaries dan yakin lanjut?')) {
            return self::FAILURE;
        }

        $query = HppVeneerBasahLog::query()
            ->where('tipe_transaksi', 'masuk')
            ->where('referensi_type', DetailHasilPaletRotary::class);

        if ($sejak = $this->option('sejak')) {
            $query->where('tanggal', '>=', $sejak);
        }

        if (! $this->option('semua')) {
            $query->where(function ($q) {
                $q->whereRaw('(COALESCE(hpp_kayu,0)+COALESCE(hpp_pekerja,0)+COALESCE(hpp_mesin,0)+COALESCE(hpp_bahan_penolong,0)) = 0')
                    ->orWhere('keterangan', 'like', '%'.HppVeneerBasahIsiService::TANDA_SEMENTARA.'%');
            });
        }

        $grup = $query->orderBy('id')->get()->groupBy(fn ($log) => $isi->kunciKombinasi($log));

        $this->info($grup->count().' kombinasi, '.$grup->sum(fn ($g) => $g->count()).' log akan diproses.');

        $status = ['final' => 0, 'sementara' => 0, 'lewati' => 0];
        $rows = [];
        $gagal = [];

        $bar = $this->output->createProgressBar($grup->count());

        foreach ($grup as $kunci => $logs) {
            $lokal = ['final' => 0, 'sementara' => 0, 'lewati' => 0];

            DB::beginTransaction();

            try {
                foreach ($logs as $log) {
                    $lokal[$isi->isiLogMasukPalet($log)]++;
                }

                if ($lokal['final'] + $lokal['sementara'] > 0) {
                    $first = $logs->first();
                    $r = $isi->hitungUlangKombinasiLog($first);

                    $rows[] = [
                        $first->id_jenis_kayu,
                        (float) $first->getRawOriginal('panjang').'×'.(float) $first->getRawOriginal('lebar').'×'.(float) $first->getRawOriginal('tebal'),
                        $first->kw ?? '-',
                        $r['logs'],
                        $r['berubah'],
                        number_format($r['avg_sebelum'] ?? 0, 0, ',', '.'),
                        number_format($r['avg_sesudah'] ?? 0, 0, ',', '.'),
                        number_format($r['nilai_sebelum'] ?? 0, 0, ',', '.'),
                        number_format($r['nilai_sesudah'] ?? 0, 0, ',', '.'),
                    ];
                }

                $apply ? DB::commit() : DB::rollBack();

                foreach ($lokal as $k => $n) {
                    $status[$k] += $n;
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $gagal[] = [$kunci, $e->getMessage()];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->line(sprintf('Log diisi → FINAL: %d | ESTIMASI: %d | DILEWATI: %d | KOMBINASI GAGAL: %d',
            $status['final'], $status['sementara'], $status['lewati'], count($gagal)));

        if ($isi->alasanLewati) {
            $this->warn('Alasan log DILEWATI:');
            foreach ($isi->alasanLewati as $alasan => $jumlah) {
                $this->line(sprintf('  %5d × %s  (contoh id log: %s)', $jumlah, $alasan, implode(', ', $isi->contohLewati[$alasan] ?? [])));
            }
        }

        $this->table(
            ['Jenis', 'Ukuran', 'KW', 'Log', 'Berubah', 'HPP avg lama', 'HPP avg baru', 'Nilai stok lama', 'Nilai stok baru'],
            $rows
        );

        if ($gagal) {
            $this->error('Kombinasi yang GAGAL (dibatalkan, tidak ada yang tersimpan untuk kombinasi ini):');
            foreach ($gagal as [$kunci, $pesan]) {
                $this->line("  {$kunci} → {$pesan}");
            }
        }

        $this->info($apply
            ? 'Selesai. Kombinasi yang berhasil sudah tersimpan.'
            : 'Dry-run selesai, tidak ada yang tersimpan. Tambahkan --apply untuk menyimpan.');

        if ($apply && ! config('hpp.veneer_basah_baru')) {
            $this->warn('Saklar HPP_VENEER_BASAH_BARU masih MATI: palet baru belum memakai alur baru.');
        }

        return $gagal ? self::FAILURE : self::SUCCESS;
    }
}