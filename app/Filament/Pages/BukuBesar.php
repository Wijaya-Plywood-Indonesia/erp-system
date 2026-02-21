<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use UnitEnum;

class BukuBesar extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected string $view = 'filament.pages.buku-besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';

    public $indukAkuns = [];
    public $filterBulan;
    public $isLoading = true;

    public function mount()
    {
        $this->filterBulan = Carbon::now()->format('Y-m');
    }

    /**
     * Dipanggil melalui wire:init di Blade
     */
    public function initLoad()
    {
        \Illuminate\Support\Facades\Log::info("=== Memulai Load Buku Besar ===");
        try {
            $this->loadData();
            \Illuminate\Support\Facades\Log::info("Data Induk Berhasil Dimuat. Jumlah: " . count($this->indukAkuns));
            $this->isLoading = false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Error saat initLoad: " . $e->getMessage());
        }
    }

    public function loadData()
    {
        $this->indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'children.children',
                        'subAnakAkuns'
                    ]);
            }
        ])->get();
    }

    /**
     * Menghitung nominal satu baris transaksi dengan proteksi nilai null
     */
    private function hitungNominal($trx)
    {
        if (!$trx) return 0;

        $qty = ($trx->hit_kbk === 'm3') ? (float)($trx->m3 ?? 0) : (float)($trx->banyak ?? 0);
        return $qty * (float)($trx->harga ?? 0);
    }

    /**
     * Mendapatkan Saldo Awal (Transaksi sebelum bulan filter)
     */
    public function getSaldoAwal($kode)
    {
        if (!$kode) return 0;

        $date = Carbon::parse($this->filterBulan)->startOfMonth();

        $trxs = JurnalUmum::where('no_akun', (string)$kode)
            ->where('tgl', '<', $date)
            ->get();

        $saldo = 0;
        foreach ($trxs as $trx) {
            $nominal = $this->hitungNominal($trx);
            $map = strtoupper($trx->map ?? '');
            $saldo += ($map === 'D' || $map === 'DEBIT') ? $nominal : -$nominal;
        }
        return $saldo;
    }

    /**
     * Transaksi hanya di bulan terpilih
     */
    public function getTransaksiByKode($kode)
    {
        if (!$kode) return collect();

        // Log untuk melihat kode apa yang dicari
        \Illuminate\Support\Facades\Log::debug("Mencari transaksi untuk kode: [" . $kode . "]");

        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where(function ($q) use ($kode) {
            $q->where('no_akun', (string)$kode)
                ->orWhere('no_akun', 'LIKE', $kode . '.%') // Antisipasi 1111 vs 1111.00
                ->orWhere('no_akun', 'LIKE', (int)$kode);
        })
            ->whereBetween('tgl', [$start, $end])
            ->get();
    }

    // Perbaikan Saldo Akun (Mendukung rekursif untuk Induk)
   public function getTotalRecursive($akun)
{
    $total = 0;

    // Ambil kode akun (anak / sub)
    $kode =
        $akun->kode_anak_akun
        ?? $akun->kode_sub_anak_akun
        ?? null;

    // ✅ Hitung saldo akun ini sendiri
    if ($kode) {
        $total += $this->getSaldoAwal($kode);

        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        $trxs = JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->get();

        foreach ($trxs as $trx) {
            $nominal = $this->hitungNominal($trx);
            $total += (strtolower($trx->map) === 'd' ? $nominal : -$nominal);
        }
    }

    // ✅ Tambahkan semua children
    if (isset($akun->children) && $akun->children->count()) {
        foreach ($akun->children as $child) {
            $total += $this->getTotalRecursive($child);
        }
    }

    if (isset($akun->subAnakAkuns) && $akun->subAnakAkuns->count()) {
        foreach ($akun->subAnakAkuns as $sub) {
            $total += $this->getTotalRecursive($sub);
        }
    }

    return $total;
}
}
