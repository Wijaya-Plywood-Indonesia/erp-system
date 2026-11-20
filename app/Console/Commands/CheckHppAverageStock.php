<?php

namespace App\Console\Commands;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use Illuminate\Console\Command;

class CheckHppAverageStock extends Command
{
    protected $signature = 'hpp:check-average-stock';

    protected $description = 'Cek selisih stok summary HPP berdasarkan log saldo';

    public function handle(): int
    {
        $checked = 0;
        $mismatches = 0;

        $this->info('Memeriksa stok HPP berdasarkan log saldo...');
        $this->newLine();

        HppAverageSummarie::query()
            ->whereNull('grade')
            ->whereNotNull('id_lahan')
            ->orderBy('id')
            ->chunk(100, function ($summaries) use (
                &$checked,
                &$mismatches
            ) {
                foreach ($summaries as $s) {
                    $checked++;

                    $logs = HppAverageLog::query()
                        ->where('id_lahan', $s->id_lahan)
                        ->where('id_jenis_kayu', $s->id_jenis_kayu)
                        ->where('panjang', $s->panjang)
                        ->orderBy('id')
                        ->get([
                            'id',
                            'stok_batang_before',
                            'stok_batang_after',
                        ]);

                    // Cari log terakhir yang membuat saldo menjadi 0
                    $titikNol = $logs
                        ->filter(
                            fn($log) =>
                            (int) $log->stok_batang_after === 0
                        )
                        ->last();

                    // Ambil log setelah titik nol
                    $sejakNol = $titikNol
                        ? $logs->filter(
                            fn($log) => $log->id > $titikNol->id
                        )
                        : $logs;

                    // Hitung saldo berdasarkan perubahan log
                    $seharusnya = (int) $sejakNol->sum(
                        fn($log) =>
                        (int) $log->stok_batang_after
                            - (int) $log->stok_batang_before
                    );

                    $saldo = (int) $s->stok_batang;
                    $selisih = $saldo - $seharusnya;

                    if ($selisih === 0) {
                        continue;
                    }

                    $mismatches++;

                    $logTerakhir = $logs->last()?->id ?? '-';
                    $idTitikNol = $titikNol?->id ?? '-';
                    $idLastLog = $s->id_last_log ?? '-';

                    $this->line(
                        "Lahan {$s->id_lahan}"
                            . " | Jenis {$s->id_jenis_kayu}"
                            . " | Pjg {$s->panjang}"
                            . " | Summary {$saldo}"
                            . " | Hitung {$seharusnya}"
                            . " | Selisih " . sprintf('%+d', $selisih)
                            . " | Nol #{$idTitikNol}"
                            . " | Last #{$logTerakhir}"
                            . " | Ref #{$idLastLog}"
                    );
                }
            });

        $this->newLine();

        $this->info(
            "Selesai: {$checked} diperiksa"
                . " | {$mismatches} tidak cocok"
        );

        return self::SUCCESS;
    }
}
