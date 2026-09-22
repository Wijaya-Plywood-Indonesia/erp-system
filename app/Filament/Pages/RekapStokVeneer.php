<?php

namespace App\Filament\Pages;

use App\Exports\RekapStokVeneerExport;
use App\Models\HppVeneerBasahSummary;
use App\Models\JenisKayu;
use App\Models\StokVeneerJadi;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
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

    public function mount(): void {}

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
                ->action(fn () => $this->exportExcel()),
        ];
    }

    public function exportExcel(): BinaryFileResponse
    {
        $built = $this->buildAllStocks();
        $localLabel = $this->getLocalLabel();
        $externalLabel = $this->getExternalLabel();
        $tanggal = Carbon::now()->translatedFormat('d F Y');
        $filename = 'Rekap_Stok_Veneer_'.Carbon::now()->format('Ymd_His').'.xlsx';

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
        $logTag = 'RekapStokVeneer['.$this->getLocalLabel().'→'.$this->getExternalLabel().']';

        if ($baseUrl === '' || empty($apiKey)) {
            Log::warning("{$logTag}: API_STOK atau INTER_API_KEY belum diisi di .env");

            return $empty;
        }

        try {
            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->timeout(10)
                ->get($baseUrl.'/api/external/rekap-stok-veneer');

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
        $allKws = [];
        $jenisKayus = JenisKayu::pluck('nama_kayu', 'id')->toArray();

        $getGroupKey = fn ($idJk, $p, $l, $t) => $idJk.'_'.(float) $p.'x'.(float) $l.'x'.(float) $t;

        $initGroup = function ($key, $idJk, $namaKayu, $p, $l, $t) use (&$allStocks) {
            if (! isset($allStocks[$key])) {
                $allStocks[$key] = [
                    'title' => (float) $p.' × '.(float) $l.' × '.(float) $t.' mm — '.$namaKayu,
                    'ukuran' => (float) $p.'x'.(float) $l.'x'.(float) $t,
                    'jenis_kayu' => $namaKayu,
                    'panjang' => (float) $p,
                    'lebar' => (float) $l,
                    'tebal' => (float) $t,
                    // "local"    = data dari DB web ini sendiri
                    // "external" = data dari API partner (web sebelah)
                    'localBasah' => [],
                    'localKering' => [],
                    'localJadi' => [],
                    'externalBasah' => [],
                    'externalKering' => [],
                    'externalJadi' => [],
                ];
            }
        };

        // ── DATA LOKAL (DB web ini) ───────────────────────────────────────────

        // 1. Lokal Basah
        foreach (HppVeneerBasahSummary::all() as $b) {
            $kw = $this->normalizeKw($b->kw);
            if ($kw === null) {
                continue;
            }
            $namaKayu = $jenisKayus[$b->id_jenis_kayu] ?? 'Unknown';
            $key = $getGroupKey($b->id_jenis_kayu, $b->panjang, $b->lebar, $b->tebal);
            $initGroup($key, $b->id_jenis_kayu, $namaKayu, $b->panjang, $b->lebar, $b->tebal);
            $allStocks[$key]['localBasah'][$kw] = ($allStocks[$key]['localBasah'][$kw] ?? 0) + $b->stok_lembar;
            $allKws[$kw] = true;
        }

        // 2. Lokal Jadi
        foreach (StokVeneerJadi::all() as $j) {
            $kw = $this->normalizeKw($j->kw_grade);
            if ($kw === null) {
                continue;
            }
            $namaKayu = $jenisKayus[$j->id_jenis_kayu] ?? 'Unknown';
            $key = $getGroupKey($j->id_jenis_kayu, $j->panjang, $j->lebar, $j->tebal);
            $initGroup($key, $j->id_jenis_kayu, $namaKayu, $j->panjang, $j->lebar, $j->tebal);
            $allStocks[$key]['localJadi'][$kw] = ($allStocks[$key]['localJadi'][$kw] ?? 0) + $j->stok_lembar;
            $allKws[$kw] = true;
        }

        // 3. Lokal Kering (ambil baris terbaru per ukuran + jenis kayu + KW)
        $ukurans = Ukuran::all()->keyBy('id');
        $keringIds = DB::table('stok_veneer_kerings')
            ->select(DB::raw('MAX(id) as max_id'))
            ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
            ->pluck('max_id');

        foreach (StokVeneerKering::whereIn('id', $keringIds)->get() as $k) {
            $kw = $this->normalizeKw($k->kw);
            if ($kw === null) {
                continue;
            }
            $ukuran = $ukurans->get($k->id_ukuran);
            if (! $ukuran) {
                continue;
            }
            $namaKayu = $jenisKayus[$k->id_jenis_kayu] ?? 'Unknown';
            $key = $getGroupKey($k->id_jenis_kayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $initGroup($key, $k->id_jenis_kayu, $namaKayu, $ukuran->panjang, $ukuran->lebar, $ukuran->tebal);
            $allStocks[$key]['localKering'][$kw] = ($allStocks[$key]['localKering'][$kw] ?? 0) + $k->stok_lembar_sesudah;
            $allKws[$kw] = true;
        }

        // ── DATA EKSTERNAL (via API_STOK) ────────────────────────────────────
        $externalData = $this->fetchExternalStocks();

        // 4. External Basah
        foreach ($externalData['basah'] ?? [] as $b) {
            $kw = $this->normalizeKw($b['kw']);
            if ($kw === null) {
                continue;
            }
            $key = $getGroupKey($b['id_jenis_kayu'], $b['panjang'], $b['lebar'], $b['tebal']);
            $initGroup($key, $b['id_jenis_kayu'], $b['nama_kayu'], $b['panjang'], $b['lebar'], $b['tebal']);
            $allStocks[$key]['externalBasah'][$kw] = ($allStocks[$key]['externalBasah'][$kw] ?? 0) + $b['stok_lembar'];
            $allKws[$kw] = true;
        }

        // 5. External Kering
        foreach ($externalData['kering'] ?? [] as $k) {
            $kw = $this->normalizeKw($k['kw']);
            if ($kw === null) {
                continue;
            }
            $key = $getGroupKey($k['id_jenis_kayu'], $k['panjang'], $k['lebar'], $k['tebal']);
            $initGroup($key, $k['id_jenis_kayu'], $k['nama_kayu'], $k['panjang'], $k['lebar'], $k['tebal']);
            $allStocks[$key]['externalKering'][$kw] = ($allStocks[$key]['externalKering'][$kw] ?? 0) + $k['stok_lembar'];
            $allKws[$kw] = true;
        }

        // 6. External Jadi
        foreach ($externalData['jadi'] ?? [] as $j) {
            $kw = $this->normalizeKw($j['kw']);
            if ($kw === null) {
                continue;
            }
            $key = $getGroupKey($j['id_jenis_kayu'], $j['panjang'], $j['lebar'], $j['tebal']);
            $initGroup($key, $j['id_jenis_kayu'], $j['nama_kayu'], $j['panjang'], $j['lebar'], $j['tebal']);
            $allStocks[$key]['externalJadi'][$kw] = ($allStocks[$key]['externalJadi'][$kw] ?? 0) + $j['stok_lembar'];
            $allKws[$kw] = true;
        }

        // Hapus ukuran yang total stoknya = 0
        $allStocks = array_filter($allStocks, function ($g) {
            foreach (['localBasah', 'localKering', 'localJadi', 'externalBasah', 'externalKering', 'externalJadi'] as $cat) {
                foreach ($g[$cat] as $v) {
                    if ($v != 0) {
                        return true;
                    }
                }
            }

            return false;
        });

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

        return [
            'stocks' => array_values($allStocks),
            'kws' => $this->sortKws(array_keys($allKws)),
        ];
    }

    protected function getViewData(): array
    {
        $built = $this->buildAllStocks();
        $allStocks = $built['stocks'];
        $allKws = $built['kws'];

        // Label tetap ditentukan dari hostname (isWijayaWeb)
        $localLabel = $this->getLocalLabel();
        $externalLabel = $this->getExternalLabel();

        // Opsi jenis kayu untuk filter dropdown
        $kayuOptions = collect($allStocks)
            ->pluck('jenis_kayu')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        // Filter jenis kayu
        if ($this->filterKayu !== '') {
            $allStocks = array_values(array_filter($allStocks,
                fn ($g) => strtolower($g['jenis_kayu']) === strtolower($this->filterKayu)
            ));
        }

        // Filter search
        if ($this->search !== '') {
            $q = strtolower($this->search);
            $allStocks = array_values(array_filter($allStocks,
                fn ($g) => str_contains(strtolower($g['title']), $q)
            ));
        }

        // Filter KW
        if ($this->filterKw !== '') {
            $filterKw = $this->filterKw;
            $kw = is_numeric($filterKw) ? (int) $filterKw : strtoupper($filterKw);
            $allStocks = array_values(array_filter($allStocks, function ($g) use ($kw) {
                $total = 0;
                foreach (['localBasah', 'localKering', 'localJadi', 'externalBasah', 'externalKering', 'externalJadi'] as $cat) {
                    $total += $g[$cat][$kw] ?? 0;
                }

                return $total != 0;
            }));
        }

        return compact('allStocks', 'allKws', 'kayuOptions', 'localLabel', 'externalLabel');
    }
}
