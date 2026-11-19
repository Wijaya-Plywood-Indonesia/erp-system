<?php

namespace App\Console\Commands;

use App\Data\HppTriplekJadiLogFilters;
use App\Services\HppTriplekJadiLogQuery;
use Carbon\Carbon;
use Database\Seeders\HppTriplekJadiLogBenchmarkSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BenchmarkHppTriplekJadiLog extends Command
{
    protected $signature = 'benchmark:hpp-log
        {--seed=0 : Jumlah baris simulasi yang di-seed sebelum benchmark (0 = pakai data yang ada)}
        {--runs=5 : Jumlah pengulangan per pengukuran (dipakai nilai median)}
        {--cleanup : Hapus baris simulasi setelah benchmark selesai}';

    protected $description = 'Benchmark query halaman Log HPP Triplek Jadi (list, filter, footer, EXPLAIN)';

    private const TABLE = 'hpp_triplek_jadi_log';
    private const FAST_MS = 100;
    private const SLOW_MS = 500;
    private const HIGH_MEMORY_MB = 64;

    public function handle(HppTriplekJadiLogQuery $query): int
    {
        if (app()->isProduction()) {
            $this->components->error('Command ini tidak boleh dijalankan di production.');

            return self::FAILURE;
        }

        DB::disableQueryLog();

        $seeder = $this->laravel->make(HppTriplekJadiLogBenchmarkSeeder::class)->setCommand($this);
        $seedRows = (int) $this->option('seed');
        $runs = max(1, (int) $this->option('runs'));

        if ($seedRows > 0) {
            $this->components->info("Seeding {$seedRows} baris simulasi...");
            $seeder->seedRows($seedRows);
        }

        $totalRows = DB::table(self::TABLE)->count();

        if ($totalRows === 0) {
            $this->components->error('Tabel kosong. Jalankan dengan --seed=100000 terlebih dahulu.');

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Database: %s | Total baris: %s | Pengulangan: %d (median, setelah 1x warm-up)',
            DB::getDatabaseName(),
            number_format($totalRows),
            $runs
        ));

        $scenarios = $this->buildScenarios();
        $timingRows = [];
        $explainRows = [];
        $findings = [];

        foreach ($scenarios as $label => $filters) {
            memory_reset_peak_usage();

            $listMs = $this->medianMs(fn() => $query->paginate($filters), $runs);
            $footerMs = $this->medianMs(fn() => $query->summary($filters, useCache: false), $runs);
            $peakMb = memory_get_peak_usage() / 1048576;
            $totalMs = $listMs + $footerMs;

            $timingRows[] = [
                $label,
                number_format($listMs, 1),
                number_format($footerMs, 1),
                number_format($totalMs, 1),
                number_format($peakMb, 1),
                $this->rate($totalMs),
            ];

            $listExplain = $query->listQuery($filters)->limit(HppTriplekJadiLogQuery::PER_PAGE + 1)->explain();
            $aggregateExplain = $query->aggregateQuery($filters)->explain();

            $scenarioFlags = ['full_scan' => false, 'filesort' => false];

            foreach (['List' => $listExplain, 'Footer' => $aggregateExplain] as $kind => $plan) {
                foreach ($plan as $planRow) {
                    $planRow = (array) $planRow;
                    [$status, $flags] = $this->evaluatePlan($planRow);
                    $scenarioFlags['full_scan'] = $scenarioFlags['full_scan'] || ($flags['full_scan'] && $kind === 'List');
                    $scenarioFlags['filesort'] = $scenarioFlags['filesort'] || $flags['filesort'];

                    $explainRows[] = [
                        $label,
                        $kind,
                        $planRow['table'] ?? '-',
                        $planRow['type'] ?? '-',
                        $planRow['key'] ?? '<fg=red>NULL</>',
                        number_format((int) ($planRow['rows'] ?? 0)),
                        $planRow['Extra'] ?? '-',
                        $status,
                    ];
                }
            }

            $findings[$label] = [
                'list_ms'   => $listMs,
                'footer_ms' => $footerMs,
                'total_ms'  => $totalMs,
                'peak_mb'   => $peakMb,
                ...$scenarioFlags,
            ];
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=white;options=bold>HASIL WAKTU EKSEKUSI</>');
        $this->table(
            ['Skenario', 'Daftar (ms)', 'Footer (ms)', 'Total (ms)', 'Peak Mem (MB)', 'Evaluasi'],
            $timingRows
        );

        $this->components->twoColumnDetail('<fg=white;options=bold>ANALISIS EXPLAIN</>');
        $this->table(
            ['Skenario', 'Query', 'Tabel', 'Tipe', 'Index dipakai', 'Rows (est.)', 'Extra', 'Status'],
            $explainRows
        );

        $this->components->twoColumnDetail('<fg=white;options=bold>EVALUASI & SARAN</>');
        $this->printAdvice($findings);

        if ($this->option('cleanup')) {
            $this->components->info('Menghapus baris simulasi...');
            $deleted = $seeder->purge();
            $this->components->info(number_format($deleted) . ' baris simulasi dihapus.');
        }

        return self::SUCCESS;
    }

    /** @return array<string, HppTriplekJadiLogFilters> */
    private function buildScenarios(): array
    {
        $woodTypeId = (string) DB::table(self::TABLE)->value('id_jenis_kayu');

        $commonSize = DB::table(self::TABLE)
            ->select('panjang', 'tebal')
            ->groupBy('panjang', 'tebal')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        $latestDate = Carbon::parse(DB::table(self::TABLE)->max('tanggal'));
        $dateTo = $latestDate->toDateString();
        $dateFrom = $latestDate->copy()->subDays(30)->toDateString();

        $length = (string) $commonSize->panjang;
        $thickness = (string) $commonSize->tebal;

        return [
            'Initial load (tanpa filter)' => new HppTriplekJadiLogFilters(),
            'Filter jenis kayu'           => new HppTriplekJadiLogFilters(jenisKayu: $woodTypeId),
            'Filter tanggal (30 hari)'    => new HppTriplekJadiLogFilters(tanggalMulai: $dateFrom, tanggalSelesai: $dateTo),
            'Filter ukuran + KW'          => new HppTriplekJadiLogFilters(panjang: $length, tebal: $thickness, kw: 'KW1'),
            'Kombinasi filter'            => new HppTriplekJadiLogFilters(
                jenisKayu: $woodTypeId,
                panjang: $length,
                tanggalMulai: $dateFrom,
                tanggalSelesai: $dateTo,
            ),
        ];
    }

    private function medianMs(callable $callback, int $runs): float
    {
        $callback(); // warm-up (buffer pool + autoload)

        $durations = [];

        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $callback();
            $durations[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($durations);

        return $durations[intdiv(count($durations), 2)];
    }

    /** @return array{0: string, 1: array{full_scan: bool, filesort: bool}} */
    private function evaluatePlan(array $row): array
    {
        $flags = ['full_scan' => false, 'filesort' => false];

        // Hanya tabel log yang dinilai; tabel kecil (jenis_kayus) diabaikan.
        if (($row['table'] ?? '') !== self::TABLE) {
            return ['<fg=green>OK</>', $flags];
        }

        $extra = (string) ($row['Extra'] ?? '');
        $flags['full_scan'] = ($row['type'] ?? '') === 'ALL';
        $flags['filesort'] = str_contains($extra, 'filesort') || str_contains($extra, 'temporary');

        return match (true) {
            $flags['full_scan'] => ['<fg=red>FULL SCAN</>', $flags],
            $flags['filesort']  => ['<fg=yellow>FILESORT</>', $flags],
            default             => ['<fg=green>OK</>', $flags],
        };
    }

    private function rate(float $ms): string
    {
        return match (true) {
            $ms < self::FAST_MS  => '<fg=green>Cepat</>',
            $ms <= self::SLOW_MS => '<fg=yellow>Perlu Optimasi</>',
            default              => '<fg=red>Lambat</>',
        };
    }

    /** @param array<string, array<string, mixed>> $findings */
    private function printAdvice(array $findings): void
    {
        $advice = [];

        foreach ($findings as $label => $f) {
            if ($f['full_scan']) {
                $advice[] = "[{$label}] Query daftar melakukan full table scan. Tambahkan index yang diawali kolom filter terpakai lalu diikuti (tanggal, id).";
            }
            if ($f['filesort']) {
                $advice[] = "[{$label}] ORDER BY tanggal DESC, id DESC memakai filesort. Pastikan ada index (kolom filter..., tanggal, id).";
            }
            if ($f['list_ms'] >= self::FAST_MS) {
                $advice[] = "[{$label}] Query daftar {$this->ms($f['list_ms'])}. Periksa EXPLAIN, pencarian KW (LIKE '%...%' tidak memakai index), dan kecilkan PER_PAGE.";
            }
            if ($f['footer_ms'] >= self::FAST_MS) {
                $advice[] = "[{$label}] Query footer {$this->ms($f['footer_ms'])}. Agregat memindai seluruh hasil filter (O(n)). Opsi: covering index (tipe_transaksi, total_lembar, total_kubikasi), cache ringkasan per filter, tabel ringkasan harian, atau filter tanggal default 30 hari.";
            }
            if ($f['peak_mb'] > self::HIGH_MEMORY_MB) {
                $advice[] = "[{$label}] Memori puncak " . number_format($f['peak_mb'], 1) . ' MB. Turunkan PER_PAGE atau kurangi kolom yang di-select.';
            }
        }

        if ($advice === []) {
            $this->components->info('Semua skenario berada pada kategori Cepat (< ' . self::FAST_MS . ' ms) tanpa full scan. Tidak ada bottleneck pada skala data ini.');

            return;
        }

        foreach (array_unique($advice) as $line) {
            $this->line(" • {$line}");
        }
    }

    private function ms(float $value): string
    {
        return number_format($value, 0) . ' ms';
    }
}
