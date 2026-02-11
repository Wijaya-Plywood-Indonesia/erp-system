<?php

namespace App\Filament\Pages;

use App\Models\IndukAkun;
use App\Models\JurnalUmum;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class BukuBesar extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected string $view = 'filament.pages.buku-besar';
    protected static ?string $navigationLabel = 'Buku Besar';
    protected static ?string $title = 'Buku Besar';

     public $indukAkuns;

    public function mount()
{
    $this->indukAkuns = IndukAkun::with([
        'anakAkuns.children.children'
    ])
    ->orderByRaw('CAST(kode_induk_akun AS UNSIGNED) ASC')
    ->get();
}

    // Ambil transaksi detail
    public function getTransaksi($noAkun)
    {
        return JurnalUmum::where('no_akun', $noAkun)
            ->orderBy('tgl')
            ->get();
    }

    public function getTransaksiByKode($kode)
{
    return \App\Models\JurnalUmum::where('no_akun', $kode)
        ->orderBy('tgl')
        ->get();
}

public function getSaldoAkun($kode)
{
    $rows = \App\Models\JurnalUmum::where('no_akun', $kode)->get();

    $saldo = 0;

    foreach ($rows as $row) {
        $qty = $row->hit_kbk === 'banyak'
            ? ($row->banyak ?? 0)
            : ($row->m3 ?? 0);

        $total = $qty * ($row->harga ?? 0);

        if ($row->map === 'D') {
            $saldo += $total;
        } else {
            $saldo -= $total;
        }
    }

    return $saldo;
}



    // Hitung saldo detail
    public function getSaldoDetail($noAkun)
    {
        return JurnalUmum::where('no_akun', $noAkun)
            ->get()
            ->sum(function ($trx) {
                $nominal = $trx->harga * (
                    $trx->hit_kbk === 'banyak'
                        ? $trx->banyak
                        : $trx->m3
                );

                return $trx->map === 'D'
                    ? $nominal
                    : -$nominal;
            });
    }

    // Hitung saldo induk (1000)
    public function getSaldoInduk($kodeInduk)
    {
        return JurnalUmum::whereHas('anakAkun.indukAkun', function ($q) use ($kodeInduk) {
            $q->where('kode_induk_akun', $kodeInduk);
        })->get()->sum(function ($trx) {
            $nominal = $trx->harga * (
                $trx->hit_kbk === 'banyak'
                    ? $trx->banyak
                    : $trx->m3
            );

            return $trx->map === 'D'
                ? $nominal
                : -$nominal;
        });
    }
}

