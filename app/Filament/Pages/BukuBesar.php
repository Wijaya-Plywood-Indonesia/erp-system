<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use Carbon\Carbon;
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
        $this->filterBulan = Carbon::now()->format('Y-m'); // Default bulan ini
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
        $qty = $trx->hit_kbk === 'banyak' ? ($trx->banyak ?? 0) : ($trx->m3 ?? 0);
        return $qty * ($trx->harga ?? 0);
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
            $saldo += ($trx->map === 'D' ? $nominal : -$nominal);
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

        // Jika ini adalah SubAnakAkun (Level Terbawah)
        if (isset($akun->kode_sub_anak_akun)) {
            // Saldo Awal + Saldo Berjalan Bulan Ini
            $total += $this->getSaldoAwal($akun->kode_sub_anak_akun);

            $start = Carbon::parse($this->filterBulan)->startOfMonth();
            $end = Carbon::parse($this->filterBulan)->endOfMonth();

            $trxs = JurnalUmum::where('no_akun', $akun->kode_sub_anak_akun)
                ->whereBetween('tgl', [$start, $end])
                ->get();
            foreach ($trxs as $trx) {
                $nominal = $this->hitungNominal($trx);
                $total += ($trx->map === 'D' ? $nominal : -$nominal);
            }
        } else {
            // Jika level Anak Akun (Punya children atau subAnakAkuns)
            if ($akun->children && $akun->children->count()) {
                foreach ($akun->children as $child) {
                    $total += $this->getTotalRecursive($child);
                }
            }
            if ($akun->subAnakAkuns && $akun->subAnakAkuns->count()) {
                foreach ($akun->subAnakAkuns as $sub) {
                    $total += $this->getTotalRecursive($sub);
                }
            }
        }

        return $total;
    }
}
