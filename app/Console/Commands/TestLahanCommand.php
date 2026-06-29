<?php

namespace App\Console\Commands;

use App\Models\HppAverageLog;
use Illuminate\Console\Command;

class TestLahanCommand extends Command
{
    protected $signature = 'test:lahan';

    protected $description = 'Test mencari boundary batch';

    public function handle(): int
    {
        $lahanId = 8;

        // Reset terakhir
        $lastReset = HppAverageLog::where('id_lahan', $lahanId)
            ->where('stok_batang_after', 0)
            ->orderByDesc('id')
            ->first();

        if (! $lastReset) {
            $this->error('Reset tidak ditemukan');

            return self::SUCCESS;
        }

        $this->info("Reset terakhir : {$lastReset->id}");
        $this->newLine();

        // Ambil 10 log sebelum reset
        $candidate = HppAverageLog::where('id_lahan', $lahanId)
            ->where('id', '<', $lastReset->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $this->table(
            [
                'ID',
                'Tipe',
                'Before',
                'After',
                'Ref Type',
                'Ref ID',
                'Keterangan',
            ],
            $candidate->map(function ($log) {

                return [
                    $log->id,
                    $log->tipe_transaksi,
                    $log->stok_batang_before,
                    $log->stok_batang_after,
                    class_basename($log->referensi_type),
                    $log->referensi_id,
                    $log->keterangan,
                ];

            })->toArray()
        );

        // Ambil log pertama (paling dekat) sebelum reset
        $boundary = $candidate->first();

        $this->newLine();

        $this->warn('Boundary yang dipilih');

        $this->table(
            [
                'ID',
                'Tipe',
                'Before',
                'After',
                'Ref ID',
            ],
            [[
                $boundary->id,
                $boundary->tipe_transaksi,
                $boundary->stok_batang_before,
                $boundary->stok_batang_after,
                $boundary->referensi_id,
            ]]
        );

        return self::SUCCESS;
    }
}
