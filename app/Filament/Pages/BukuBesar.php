<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Carbon\Carbon;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Facades\DB;
use Throwable;

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
        $this->filterBulan = Carbon::now()->format('Y-m'); // Default bulan ini
    }

    public function initLoad()
    {
        $this->loadData();
        $this->isLoading = false;
    }

    public function loadData()
    {
        $this->indukAkuns = IndukAkun::with([
            'anakAkuns' => function ($query) {
                $query->whereNull('parent')
                    ->with([
                        'children.children', // rekursif 2 level
                        'subAnakAkuns'
                    ]);
            }
        ])->get();
    }

    // Fungsi menghitung nominal satu baris transaksi
    private function hitungNominal($trx)
    {
        $mode = strtolower($trx->hit_kbk ?? '');

        // Jika data lama (hit_kbk null/kosong)
        if ($mode === '' || $mode === null) {
            return $trx->harga ?? 0;
        }

        // Jika banyak
        if ($mode === 'b' || $mode === 'banyak') {
            return ($trx->banyak ?? 0) * ($trx->harga ?? 0);
        }

        // Jika kubikasi
        return ($trx->m3 ?? 0) * ($trx->harga ?? 0);
    }

    // Mendapatkan Saldo Awal (Transaksi sebelum bulan filter)
    public function getSaldoAwal($kode)
    {
        $date = Carbon::parse($this->filterBulan)->startOfMonth();

        $trxs = JurnalUmum::where('no_akun', $kode)
            ->where('tgl', '<', $date)
            ->get();

        $saldo = 0;
        foreach ($trxs as $trx) {
            $nominal = $this->hitungNominal($trx);
            $saldo += (strtolower($trx->map) === 'd' ? $nominal : -$nominal);
        }
        return $saldo;
    }

    // Transaksi hanya di bulan terpilih
    public function getTransaksiByKode($kode)
    {
        $start = Carbon::parse($this->filterBulan)->startOfMonth();
        $end = Carbon::parse($this->filterBulan)->endOfMonth();

        return JurnalUmum::where('no_akun', $kode)
            ->whereBetween('tgl', [$start, $end])
            ->orderBy('tgl')
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
