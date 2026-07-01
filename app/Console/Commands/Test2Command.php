<?php

namespace App\Console\Commands;

use App\Models\DetailTurusanKayu;
use Illuminate\Console\Command;

class Test2Command extends Command
{
    /**
     * php artisan test2 8
     */
    protected $signature = 'test2 {id=8}';

    protected $description = 'Test query asli DetailTurusanKayu tanpa HppAverageLog';

    public function handle(): int
    {
        $lahanId = (int) $this->argument('id');

        $data = DetailTurusanKayu::with([
            'lahan',
            'kayuMasuk.penggunaanSupplier',
            'kayuMasuk.notaKayu',
        ])
            ->where('lahan_id', $lahanId)
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

        $this->info('=== TESTCASE 2 ===');
        $this->info("LAHAN : {$lahanId}");
        $this->newLine();

        if ($data->isEmpty()) {
            $this->warn('Tidak ada data.');

            return self::SUCCESS;
        }

        $this->table(
            [
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
            ],
            $data->toArray()
        );

        return self::SUCCESS;
    }
}
