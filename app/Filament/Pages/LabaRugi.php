<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\JurnalTiga;
use App\Models\AnakAkun;
use BackedEnum;
use UnitEnum;

class LabaRugi extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $title = 'Laba Rugi';
    protected string $view = 'filament.pages.laba-rugi';

    // ================= TOTAL =================
    public $totalPendapatan = 0;
    public $hpp = 0;
    public $pendapatanKotor = 0;
    public $totalBiaya = 0;
    public $pendapatanSebelumPajak = 0;
    public $bebanPajak = 0;
    public $labaBersih = 0;

    // ================= DETAIL =================
    public $akunPendapatan = [];
    public $akunBiaya = [];

    public function mount()
    {
        /*
        |--------------------------------------------------------------------------
        | PENDAPATAN (Parent 4000)
        |--------------------------------------------------------------------------
        */

        $pendapatanAkun = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 4000);
        })
            ->whereNull('parent')
            ->orderBy('kode_anak_akun')
            ->get();

        foreach ($pendapatanAkun as $akun) {

            $total = JurnalTiga::where('akun_seratus', $akun->kode_anak_akun)
                ->sum('total');


            $this->akunPendapatan[] = [
                'kode'  => $akun->kode_anak_akun,
                'nama'  => $akun->nama_anak_akun,
                'total' => $total,
            ];

            $this->totalPendapatan += $total;
        }

        /*
        |--------------------------------------------------------------------------
        | HPP
        |--------------------------------------------------------------------------
        */

        $this->hpp =
    JurnalTiga::where('detail', 'like', '%hpp%')
        ->sum('total');

        $this->pendapatanKotor = $this->totalPendapatan + $this->hpp;

        /*
        |--------------------------------------------------------------------------
        | BIAYA (Parent 5000 kecuali 5900)
        |--------------------------------------------------------------------------
        */

        $biayaAkun = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 5000);
        })
            ->whereNull('parent')
            ->where('kode_anak_akun', '!=', 5900)
            ->orderBy('kode_anak_akun')
            ->get();

        foreach ($biayaAkun as $akun) {

            $total = JurnalTiga::where('akun_seratus', $akun->kode_anak_akun)
                ->sum('total');


            $this->akunBiaya[] = [
                'kode'  => $akun->kode_anak_akun,
                'nama'  => $akun->nama_anak_akun,
                'total' => $total,
            ];

            $this->totalBiaya += $total;
        }

        /*
        |--------------------------------------------------------------------------
        | SEBELUM PAJAK
        |--------------------------------------------------------------------------
        */

        $this->pendapatanSebelumPajak =
            $this->pendapatanKotor + $this->totalBiaya;

        /*
        |--------------------------------------------------------------------------
        | PAJAK (5900)
        |--------------------------------------------------------------------------
        */

        $this->bebanPajak =
    JurnalTiga::where('akun_seratus', 5900)
        ->sum('total');

        /*
        |--------------------------------------------------------------------------
        | LABA BERSIH
        |--------------------------------------------------------------------------
        */

        $this->labaBersih =
            $this->pendapatanSebelumPajak + $this->bebanPajak;
    }
}
