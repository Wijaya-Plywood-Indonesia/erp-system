<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use UnitEnum;

class RekapStokVeneer extends Page implements HasForms
{
    use InteractsWithForms;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected string $view = 'filament.pages.rekap-stok-veneer';
    protected static UnitEnum|string|null $navigationGroup = 'Laporan';
    protected static ?string $title = 'Rekap Stok Veneer';
    protected static ?string $navigationLabel = 'Rekap Stok Veneer';
    protected static ?int $navigationSort = 20;

    public string $search = '';
    public string $filterKayu = '';
    public string $filterKw = '';

    public string $sortBy = 'ukuran';

    public function mount(): void
    {
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn() => $this->exportExcel()),
        ];
    }

    public function exportExcel(): BinaryFileResponse
    {
        $built = $this->buildAllStocks();
        $localLabel = $this->getLocalLabel();
        $externalLabel = $this->getExternalLabel();
        $tanggal = Carbon::now()->translatedFormat('d F Y');
        $filename = 'Rekap_Stok_Veneer_' . Carbon::now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new RekapStokVeneerExport(
                stocks: $built['stocks'],
                kws: $built['kws'],
                localLabel: $localLabel,
                externalLabel: $externalLabel,
                tanggal: $tanggal,
            ),
            $filename
        );
    }

    /**
     * Deteksi apakah web ini adalah Wijaya berdasarkan hostname.
     * Hanya dipakai untuk menentukan LABEL (Wijaya / Wahana).
     */
    protected function isWijayaWeb(): bool
    {
        $host = request()->getHost();

        return in_array($host, ['kayu.wijayaplywoods.com', 'prarelease.wijayaplywoods.com']);
    }

    protected function getLocalLabel(): string
    {
        return $this->isWijayaWeb() ? 'Wijaya' : 'Wahana';
    }

    protected function getExternalLabel(): string
    {
        return $this->isWijayaWeb() ? 'Wahana' : 'Wijaya';
    }

    /**
     * Normalisasi nilai KW: trim + uppercase.
     * Nilai numerik dikembalikan sebagai int supaya key array konsisten.
     */
    protected function normalizeKw(mixed $raw): string|int|null
    {
        $kw = strtoupper(trim((string) $raw));
        if ($kw === '') {
            return null;
        }

        return is_numeric($kw) ? (int) $kw : $kw;
    }

    /**
     * Ambil data stok veneer dari API partner (web sebelah).
     *
     * URL diambil dari satu variabel env: API_STOK
     *  - di web Wijaya, API_STOK menunjuk ke Wahana
     *  - di web Wahana, API_STOK menunjuk ke Wijaya
     *
     * Jika API gagal / timeout / env kosong, kembalikan array kosong
     * agar halaman tetap tampil dengan data lokal saja.
     */
    protected function fetchExternalStocks(): array
    {
        $empty = ['basah' => [], 'kering' => [], 'jadi' => []];
        $baseUrl = rtrim((string) config('services.stok_partner.url'), '/');
        $apiKey = config('services.stok_partner.key');
        $logTag = 'RekapStokVeneer[' . $this->getLocalLabel() . '→' . $this->getExternalLabel() . ']';

        if ($baseUrl === '' || empty($apiKey)) {
            Log::warning("{$logTag}: API_STOK atau INTER_API_KEY belum diisi di .env");

            return $empty;
        }

        try {
            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(10)
                ->get($baseUrl . '/api/external/rekap-stok-veneer');

            if ($response->successful() && $response->json('status') === 'success') {
                return $response->json('data', $empty);
            }

            Log::warning("{$logTag}: API returned non-success", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error("{$logTag}: Gagal fetch data eksternal", ['error' => $e->getMessage()]);
        }

        return $empty;
    }

    /**
     * Urutkan daftar KW: angka dulu (asc), lalu huruf (asc).
     */
    protected function sortKws(array $kws): array
    {
        usort($kws, function ($a, $b) {
            $aNum = is_int($a);
            $bNum = is_int($b);
            if ($aNum && $bNum) {
                return $a <=> $b;
            }
            if ($aNum) {
                return -1;
            }
            if ($bNum) {
                return 1;
            }

            return strcmp($a, $b);
        });

        return $kws;
    }

    protected function buildAllStocks(): array
    {
        $allStocks = [];
        $jenisKayus = \App\Models\JenisKayu::pluck('nama_kayu', 'id')->toArray();

        $getGroupKey = fn($idJk, $p, $l, $t) =>
            $idJk . '_' . (float) $p . 'x' . (float) $l . 'x' . (float) $t;

        $initGroup = function ($key, $idJk, $p, $l, $t) use (&$allStocks, $jenisKayus) {
            if (!isset($allStocks[$key])) {
                $namaKayu = $jenisKayus[$idJk] ?? 'Unknown';
                $allStocks[$key] = [
                    'title' => (float) $p . ' × ' . (float) $l . ' × ' . (float) $t . ' mm — ' . $namaKayu,
                    'ukuran' => (float) $p . 'x' . (float) $l . 'x' . (float) $t,
                    'jenis_kayu' => $namaKayu,
                    'panjang' => (float) $p,
                    'lebar' => (float) $l,
                    'tebal' => (float) $t,
                    'wijayaBasah' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                    'wijayaKering' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                    'wijayaJadi' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 'AF' => 0],
                ];
            }
        };

        $normalizeKw = function ($raw) {
            $kw = strtoupper(trim((string) $raw));
            if (!in_array($kw, ['1', '2', '3', '4', '5', 'AF']))
                return null;
            return is_numeric($kw) ? (int) $kw : $kw;
        };

        // 1. Basah
        foreach (\App\Models\HppVeneerBasahSummary::all() as $b) {
            $kw = $normalizeKw($b->kw);
            if ($kw === null)
                continue;
            $key = $getGroupKey($b->id_jenis_kayu, $b->panjang, $b->lebar, $b->tebal);
            $initGroup($key, $b->id_jenis_kayu, $b->panjang, $b->lebar, $b->tebal);
            $allStocks[$key]['wijayaBasah'][$kw] += $b->stok_lembar;
        }

        // 2. Jadi
        foreach (\App\Models\StokVeneerJadi::all() as $j) {
            $kw = $normalizeKw($j->kw_grade);
            if ($kw === null)
                continue;
            $key = $getGroupKey($j->id_jenis_kayu, $j->panjang, $j->lebar, $j->tebal);
            $initGroup($key, $j->id_jenis_kayu, $j->panjang, $j->lebar, $j->tebal);
            $allStocks[$key]['wijayaJadi'][$kw] += $j->stok_lembar;
        }

        // 3. Kering
        $ukurans = \App\Models\Ukuran::all()->keyBy('id');
        $keringIds = DB::table('stok_veneer_kerings')
            ->select(DB::raw('MAX(id) as max_id'))
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->pluck('max_id');

        foreach (\App\Models\StokVeneerKering::whereIn('id', $keringIds)->get() as $k) {
            $kw = $normalizeKw($k->kw);
            if ($kw === null)
                continue;
            $ukuran = $ukurans->get($k->id_ukuran);
            if (!$ukuran)
                continue;
            $key = $getGroupKey($k->id_jenis_kayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $initGroup($key, $k->id_jenis_kayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $allStocks[$key]['wijayaKering'][$kw] += $k->stok_lembar_sesudah;
        }

        // Hapus ukuran yang total stoknya = 0
        $allStocks = array_filter($allStocks, function ($g) {
            foreach (['wijayaBasah', 'wijayaKering', 'wijayaJadi'] as $cat) {
                foreach ($g[$cat] as $v) {
                    if ($v != 0)
                        return true;
                }
            }
            return false;
        });


        usort($allStocks, fn($a, $b) => strcmp($a['title'], $b['title']));

        if ($this->sortBy === 'jenis_kayu') {
            usort($allStocks, function ($a, $b) {
                $cmp = strcmp($a['jenis_kayu'], $b['jenis_kayu']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                if ($a['tebal'] != $b['tebal']) {
                    return $a['tebal'] <=> $b['tebal'];
                } // Ascending: tertipis ke tertebal
                if ($a['panjang'] != $b['panjang']) {
                    return $a['panjang'] <=> $b['panjang'];
                }

                return $a['lebar'] <=> $b['lebar'];
            });
        } else {
            // Urutkan berdasarkan TEBAL terlebih dahulu (tertipis ke tertebal),
            // baru panjang, lebar, lalu jenis kayu sebagai tie-breaker terakhir.
            usort($allStocks, function ($a, $b) {
                if ($a['tebal'] != $b['tebal']) {
                    return $a['tebal'] <=> $b['tebal'];
                }
                if ($a['panjang'] != $b['panjang']) {
                    return $a['panjang'] <=> $b['panjang'];
                }
                if ($a['lebar'] != $b['lebar']) {
                    return $a['lebar'] <=> $b['lebar'];
                }

                return strcmp($a['jenis_kayu'], $b['jenis_kayu']);
            });
        }

        return array_values($allStocks);
    }

    protected function getViewData(): array
    {
        $allStocks = $this->buildAllStocks();

        // Opsi jenis kayu untuk filter dropdown
        $kayuOptions = collect($allStocks)
            ->pluck('jenis_kayu')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Terapkan filter
        if ($this->filterKayu !== '') {
            $allStocks = array_values(array_filter(
                $allStocks,
                fn($g) => strtolower($g['jenis_kayu']) === strtolower($this->filterKayu)
            ));
        }

        if ($this->search !== '') {
            $q = strtolower($this->search);
            $allStocks = array_values(array_filter(
                $allStocks,
                fn($g) => str_contains(strtolower($g['title']), $q)
            ));
        }

        if ($this->filterKw !== '') {
            $filterKw = $this->filterKw;
            $kw = is_numeric($filterKw) ? (int) $filterKw : strtoupper($filterKw);
            $allStocks = array_values(array_filter($allStocks, function ($g) use ($kw) {
                $basah = $g['wijayaBasah'][$kw] ?? 0;
                $kering = $g['wijayaKering'][$kw] ?? 0;
                $jadi = $g['wijayaJadi'][$kw] ?? 0;
                return ($basah + $kering + $jadi) != 0;
            }));
        }

        return compact('allStocks', 'kayuOptions');
    }
}
