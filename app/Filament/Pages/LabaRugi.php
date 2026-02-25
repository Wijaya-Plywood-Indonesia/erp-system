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

    // ================= FILTER =================
    public $useCustomFilter = false;
    public $selectedAkun = [];

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
        $this->hitung();
    }

    public function updatedUseCustomFilter()
    {
        $this->hitung();
    }

    public function updatedSelectedAkun()
    {
        $this->hitung();
    }

    private function hitung()
    {
        // RESET
        $this->totalPendapatan = 0;
        $this->hpp = 0;
        $this->pendapatanKotor = 0;
        $this->totalBiaya = 0;
        $this->pendapatanSebelumPajak = 0;
        $this->bebanPajak = 0;
        $this->labaBersih = 0;
        $this->akunPendapatan = [];
        $this->akunBiaya = [];

        /*
        |------------------------------------------------------------------
        | PENDAPATAN (4000)
        |------------------------------------------------------------------
        */

        $pendapatanQuery = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 4000);
        })
        ->whereNull('parent');

        if ($this->useCustomFilter && !empty($this->selectedAkun)) {
            $pendapatanQuery->whereIn('kode_anak_akun', $this->selectedAkun);
        }

        $pendapatanAkun = $pendapatanQuery
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
        |------------------------------------------------------------------
        | HPP (TIDAK DIUBAH)
        |------------------------------------------------------------------
        */

        $this->hpp = JurnalTiga::where('detail', 'like', '%hpp%')
            ->sum('total');

        $this->pendapatanKotor = $this->totalPendapatan + $this->hpp;

        /*
        |------------------------------------------------------------------
        | BIAYA (5000 kecuali 5900)
        |------------------------------------------------------------------
        */

        $biayaQuery = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 5000);
        })
        ->whereNull('parent')
        ->where('kode_anak_akun', '!=', 5900);

        if ($this->useCustomFilter && !empty($this->selectedAkun)) {
            $biayaQuery->whereIn('kode_anak_akun', $this->selectedAkun);
        }

        $biayaAkun = $biayaQuery
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
        |------------------------------------------------------------------
        | RUMUS KAMU (TIDAK DIUBAH)
        |------------------------------------------------------------------
        */

        $this->pendapatanSebelumPajak =
            $this->pendapatanKotor + $this->totalBiaya;

        $this->bebanPajak =
            JurnalTiga::where('akun_seratus', 5900)
                ->sum('total');

        $this->labaBersih =
            $this->pendapatanSebelumPajak + $this->bebanPajak;
    }

    public function getDaftarAkunProperty()
    {
        return AnakAkun::whereHas('indukAkun', function ($q) {
            $q->whereIn('kode_induk_akun', [4000, 5000]);
        })
        ->whereNull('parent')
        ->pluck('nama_anak_akun', 'kode_anak_akun');
    }
}