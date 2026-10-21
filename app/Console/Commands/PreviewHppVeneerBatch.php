<?php

namespace App\Console\Commands;

use App\Models\PenggunaanLahanRotary;
use App\Services\HppVeneerBasahBatchService;
use Illuminate\Console\Command;

class PreviewHppVeneerBatch extends Command
{
    /**
     * Contoh:
     *   php artisan hpp:veneer-batch-preview --lahan=1 --limit=5
     *   php artisan hpp:veneer-batch-preview --closure=1588
     */
    protected $signature = 'hpp:veneer-batch-preview
                            {--lahan= : Filter id_lahan}
                            {--closure= : Hanya satu id penggunaan_lahan_rotaries (closure)}
                            {--limit=10 : Jumlah closure terbaru}
                            {--outlier : Pindai SEMUA batch, tampilkan yang HPP/m³-nya di atas --batas atau m³ veneer-nya sangat kecil}
                            {--batas=3000000 : Batas HPP total per m³ untuk mode --outlier}';

    protected $description = 'Pratinjau HPP veneer basah per batch lahan (rumus sama dengan Persentase Kayu). Tidak menulis apa pun ke database.';

    public function handle(HppVeneerBasahBatchService $service): int
    {
        $query = PenggunaanLahanRotary::query()->where('jumlah_batang', '>', 0);

        if ($id = $this->option('closure')) {
            $query->where('id', $id);
        }
        if ($lahan = $this->option('lahan')) {
            $query->where('id_lahan', $lahan);
        }

        if ($this->option('outlier')) {
            return $this->tampilkanOutlier($service, $query);
        }

        $closures = $query->orderByDesc('created_at')->limit((int) $this->option('limit'))->get();

        if ($closures->isEmpty()) {
            $this->warn('Tidak ada closure yang cocok.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($closures as $closure) {
            $k = $service->komponenUntukClosure($closure);
            $rows[] = [
                $closure->id,
                $closure->id_lahan,
                count($k['ids_penggunaan_lahan']),
                number_format($k['m3_kayu'], 4, ',', '.'),
                number_format($k['m3_veneer'], 4, ',', '.'),
                number_format($k['total_poin'], 0, ',', '.'),
                number_format($k['hpp_kayu'], 0, ',', '.'),
                number_format($k['hpp_pekerja'], 0, ',', '.'),
                number_format($k['hpp_mesin'], 0, ',', '.'),
                number_format($k['hpp_bahan_penolong'], 0, ',', '.'),
                number_format($k['hpp_total'], 0, ',', '.'),
                $k['punya_poin'] ? '' : 'POIN 0!',
            ];
        }

        $this->table(
            ['Closure', 'Lahan', 'Baris', 'm³ kayu', 'm³ veneer', 'Poin', 'Kayu/m³', 'Upah/m³', 'Susut/m³', 'Bahan/m³', 'TOTAL/m³', 'Catatan'],
            $rows
        );
        $this->line('Bandingkan kolom Kayu/m³ dengan "Veneer" dan TOTAL/m³ dengan "Veneer+Ongkos+Susut+Bahan Penolong" di halaman Persentase Kayu.');

        return self::SUCCESS;
    }

    /**
     * Cari batch yang HPP/m³-nya tidak wajar (biasanya poin kayu besar tapi output veneer tercatat kecil).
     * Cocokkan baris ini dengan halaman Persentase Kayu: angkanya harus sama; jika ya, penyebabnya
     * ada di data batch (mis. palet belum tercatat / hasil real salah), bukan di rumus.
     */
    protected function tampilkanOutlier(HppVeneerBasahBatchService $service, $query): int
    {
        $batas = (float) $this->option('batas');
        $closures = $query->orderBy('created_at')->get();
        $this->info("Memindai {$closures->count()} batch (bisa beberapa menit)...");

        $bar = $this->output->createProgressBar($closures->count());
        $hasil = [];

        foreach ($closures as $closure) {
            $k = $service->komponenUntukClosure($closure);
            $bar->advance();

            if ($k['punya_poin'] && ($k['hpp_total'] > $batas || $k['m3_veneer'] < 0.5)) {
                $hasil[] = [$closure, $k];
            }
        }
        $bar->finish();
        $this->newLine(2);

        usort($hasil, fn ($a, $b) => $b[1]['hpp_total'] <=> $a[1]['hpp_total']);

        $rows = array_map(fn ($h) => [
            $h[0]->id,
            $h[0]->id_lahan,
            substr((string) $h[0]->created_at, 0, 10),
            number_format($h[1]['m3_kayu'], 4, ',', '.'),
            number_format($h[1]['m3_veneer'], 4, ',', '.'),
            number_format($h[1]['total_poin'], 0, ',', '.'),
            number_format($h[1]['hpp_total'], 0, ',', '.'),
        ], $hasil);

        $this->table(['Closure', 'Lahan', 'Tgl', 'm³ kayu', 'm³ veneer', 'Poin', 'TOTAL/m³'], $rows);
        $this->line(count($rows).' batch di atas Rp '.number_format($batas, 0, ',', '.').'/m³ atau m³ veneer < 0,5.');

        return self::SUCCESS;
    }
}