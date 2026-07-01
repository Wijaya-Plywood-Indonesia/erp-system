<?php

namespace App\Console\Commands;

use App\Models\DetailTurusanKayu;
use App\Models\HppAverageLog;
use Illuminate\Console\Command;

class Test3Command extends Command
{
    /**
     * php artisan test3 8
     */
    protected $signature = 'test3 {id=25}';

    protected $description = 'Test Batch Aktif menggunakan Last Nota Before Reset';

    public function handle(): int
    {
        $lahanId = (int) $this->argument('id');

        /*
        |--------------------------------------------------------------------------
        | Reset terakhir (after = 0)
        |--------------------------------------------------------------------------
        */

        $lastReset = HppAverageLog::where('id_lahan', $lahanId)
            ->where('tipe_transaksi', 'keluar')
            ->where('stok_batang_after', 0)
            ->orderByDesc('id')
            ->first();

        // Nilai awal null sebagai penanda jika reset tidak ditemukan
        $startNotaId = null;

        if (! $lastReset) {
            $this->warn("Reset tidak ditemukan untuk Lahan ID: {$lahanId}. Menampilkan seluruh data pada lahan tersebut.");
            $this->newLine();
        } else {
            /*
            |--------------------------------------------------------------------------
            | Nota terakhir sebelum reset
            |--------------------------------------------------------------------------
            */

            $lastNotaBeforeReset = HppAverageLog::where('id_lahan', $lahanId)
                ->where('tipe_transaksi', 'masuk')
                ->where('id', '<', $lastReset->id)
                ->orderByDesc('id')
                ->first();

            if (! $lastNotaBeforeReset) {
                $this->error('Nota sebelum reset tidak ditemukan.');

                return self::FAILURE;
            }

            $startNotaId = $lastNotaBeforeReset->referensi_id;

            $this->info("Reset Log     : {$lastReset->id}");
            $this->info("Start Log     : {$lastNotaBeforeReset->id}");
            $this->info("Start Nota ID : {$startNotaId}");
        }

        $this->info("LAHAN         : {$lahanId}");
        $this->newLine();

        /*
        |--------------------------------------------------------------------------
        | Query DetailTurusanKayu
        |--------------------------------------------------------------------------
        */

        $data = DetailTurusanKayu::with([
            'lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.notaKayu',
        ])
            ->where('lahan_id', $lahanId)
            // Filter nota hanya jalan jika $startNotaId memiliki nilai (tidak null)
            ->when($startNotaId, function ($q) use ($startNotaId) {
                $q->whereHas('kayuMasuk.notaKayu', function ($query) use ($startNotaId) {
                    $query->where('id', '>=', $startNotaId);
                });
            })
            ->get()
            ->groupBy('id_kayu_masuk')
            ->map(function ($rows) {
                $first = $rows->first();

                return [
                    'ID Kayu' => $first->id_kayu_masuk,
                    'ID Nota' => $first->kayuMasuk?->notaKayu?->id,
                    'No Nota' => $first->kayuMasuk?->notaKayu?->no_nota,
                    'Kode Lahan' => $first->lahan?->kode_lahan,
                    'Seri' => $first->kayuMasuk?->seri,
                    'Supplier' => trim($first->kayuMasuk?->penggunaanSupplier?->nama_supplier),
                    'Status Pelunasan' => $first->kayuMasuk?->notaKayu?->status_pelunasan,
                    'Batang' => $rows->sum('kuantitas'),
                    'Kubikasi' => round($rows->sum('kubikasi'), 4),
                    'Panjang' => $rows->pluck('panjang')->unique()->sort()->implode(', '),
                    'Grade' => $rows->pluck('grade')->unique()->sort()->implode(', '),
                ];
            })
            ->sortBy('ID Nota')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | POP Nota Sebelum Reset
        |--------------------------------------------------------------------------
        */

        // Fungsi reject() hanya dieksekusi jika data disaring dari titik reset tertentu
        if ($startNotaId) {
            $data = $data
                ->reject(function ($row) use ($startNotaId) {
                    return $row['ID Nota'] == $startNotaId;
                })
                ->values();
        }

        /*
        |--------------------------------------------------------------------------
        | Output
        |--------------------------------------------------------------------------
        */

        if ($data->isEmpty()) {
            $this->warn('Tidak ada data.');

            return self::SUCCESS;
        }

        $this->table([
            'ID Kayu',
            'ID Nota',
            'No Nota',
            'Kode Lahan',
            'Seri',
            'Supplier',
            'Status Pelunasan',
            'Batang',
            'Kubikasi',
            'Panjang',
            'Grade',
        ], $data->toArray());

        return self::SUCCESS;
    }
}
