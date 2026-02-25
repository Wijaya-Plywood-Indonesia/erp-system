<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\JurnalUmum;
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
    public $tanggalAwal = null;
    public $tanggalAkhir = null;

    // ================= DATA =================
    public $totalPendapatan = 0;
    public $hpp = 0;
    public $pendapatanKotor = 0;
    public $totalBiaya = 0;
    public $pendapatanSebelumPajak = 0;
    public $bebanPajak = 0;
    public $labaBersih = 0;
    public $daftarAkun = [];
    public $selectedAkun = [];
    public $akunPendapatan = [];
    public $akunBiaya = [];
    public $akunLainnya = [];
public $totalLainnya = 0;

    public function mount()
    {
        $this->loadDaftarAkunFromGroup();
        $this->hitung();
    }

    private function loadDaftarAkunFromGroup()
    {
        $group = \App\Models\AkunGroup::where('nama', 'Laba Rugi')
            ->with('anakAkuns')
            ->first();

        if (!$group) {
            $this->daftarAkun = [];
            return;
        }

        $this->daftarAkun = $group->anakAkuns
            ->sortBy('kode_anak_akun')
            ->mapWithKeys(function ($anak) {
                return [
                    $anak->kode_anak_akun => $anak->nama_anak_akun
                ];
            })
            ->toArray();
    }

    public function updated($property)
    {
        if (in_array($property, [
            'useCustomFilter',
            'tanggalAwal',
            'tanggalAkhir',
            'selectedAkun' // ← TAMBAHKAN INI
        ])) {
            $this->resetData();
            $this->hitung();
        }
    }

    public function updatedSelectedAkun()
{
    $this->resetData();
    $this->hitung();
}

    private function resetData()
    {
        $this->totalPendapatan = 0;
        $this->hpp = 0;
        $this->pendapatanKotor = 0;
        $this->totalBiaya = 0;
        $this->pendapatanSebelumPajak = 0;
        $this->bebanPajak = 0;
        $this->labaBersih = 0;
        $this->akunPendapatan = [];
        $this->akunBiaya = [];
        $this->akunLainnya = [];
$this->totalLainnya = 0;
    }

    private function baseQuery()
    {
        $query = JurnalUmum::query();

        if ($this->useCustomFilter && $this->tanggalAwal && $this->tanggalAkhir) {
            $query->whereBetween('tanggal', [
                $this->tanggalAwal,
                $this->tanggalAkhir
            ]);
        }

        return $query;
    }

    private function hitung()
    {
        // ================= PENDAPATAN =================
        $pendapatanAkun = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 4000);
        })
            ->whereNull('parent')
            ->orderBy('kode_anak_akun')
            ->get();

        // 🔥 FILTER DI SINI
        if ($this->useCustomFilter && !empty($this->selectedAkun)) {
            $pendapatanAkun = $pendapatanAkun->whereIn(
                'kode_anak_akun',
                $this->selectedAkun
            );
        }

        foreach ($pendapatanAkun as $akun) {

            $total = $this->sumFromJurnalUmum($akun->kode_anak_akun);

            $this->akunPendapatan[] = [
                'kode'  => $akun->kode_anak_akun,
                'nama'  => $akun->nama_anak_akun,
                'total' => $total,
            ];

            $this->totalPendapatan += $total;
        }

        // ================= HPP =================
        $this->hpp = $this->sumHpp();

        $this->pendapatanKotor =
            $this->totalPendapatan + $this->hpp;

        // ================= BIAYA =================
        $biayaAkun = AnakAkun::whereHas('indukAkun', function ($q) {
            $q->where('kode_induk_akun', 5000);
        })
            ->whereNull('parent')
            ->where('kode_anak_akun', '!=', 5900)
            ->orderBy('kode_anak_akun')
            ->get();

        // 🔥 FILTER DI SINI
        if ($this->useCustomFilter && !empty($this->selectedAkun)) {
            $biayaAkun = $biayaAkun->whereIn(
                'kode_anak_akun',
                $this->selectedAkun
            );
        }

        foreach ($biayaAkun as $akun) {

            $total = $this->sumFromJurnalUmum($akun->kode_anak_akun);

            $this->akunBiaya[] = [
                'kode'  => $akun->kode_anak_akun,
                'nama'  => $akun->nama_anak_akun,
                'total' => $total,
            ];

            $this->totalBiaya += $total;
        }

        $this->pendapatanSebelumPajak =
            $this->pendapatanKotor + $this->totalBiaya;

        $this->bebanPajak =
            $this->sumFromJurnalUmum(5900);

        $this->labaBersih =
            $this->pendapatanSebelumPajak + $this->bebanPajak;

        // ================= AKUN LAINNYA =================
if ($this->useCustomFilter && !empty($this->selectedAkun)) {

    $akunSemua = AnakAkun::whereIn('kode_anak_akun', $this->selectedAkun)
        ->whereNull('parent')
        ->get();

    foreach ($akunSemua as $akun) {

        $kodeInduk = $akun->indukAkun->kode_induk_akun ?? null;

        if (!in_array($kodeInduk, [4000, 5000])) {

            $total = $this->sumFromJurnalUmum($akun->kode_anak_akun);

            $this->akunLainnya[] = [
                'kode' => $akun->kode_anak_akun,
                'nama' => $akun->nama_anak_akun,
                'total' => $total,
            ];

            $this->totalLainnya += $total;
        }
    }
}
    }

    private function sumFromJurnalUmum($akunRatusan)
    {
        return $this->baseQuery()
            ->get()
            ->map(function ($row) {

                $hit   = strtolower(trim((string) ($row->hit_kbk ?? '')));
                $harga = (float) ($row->harga ?? 0);
                $byk   = (float) ($row->banyak ?? 0);
                $m3    = (float) ($row->m3 ?? 0);

                if ($hit === 'b') {
                    $nominal = $byk * $harga;
                } elseif ($hit === 'm') {
                    $nominal = $m3 * $harga;
                } else {
                    $nominal = $harga;
                }

                $signed = strtoupper($row->map) === 'D'
                    ? $nominal
                    : -$nominal;

                $akunPuluhan = floor(((int) explode('.', $row->no_akun)[0]) / 10) * 10;
                $akunRatusanRow = floor($akunPuluhan / 100) * 100;

                return [
                    'akun_ratusan' => $akunRatusanRow,
                    'total' => $signed,
                ];
            })
            ->where('akun_ratusan', $akunRatusan)
            ->sum('total');
    }

    private function sumHpp()
    {
        return $this->baseQuery()
            ->get()
            ->filter(function ($row) {
                return str_contains(strtolower($row->nama_akun ?? ''), 'hpp');
            })
            ->map(function ($row) {

                $hit   = strtolower(trim((string) ($row->hit_kbk ?? '')));
                $harga = (float) ($row->harga ?? 0);
                $byk   = (float) ($row->banyak ?? 0);
                $m3    = (float) ($row->m3 ?? 0);

                if ($hit === 'b') {
                    $nominal = $byk * $harga;
                } elseif ($hit === 'm') {
                    $nominal = $m3 * $harga;
                } else {
                    $nominal = $harga;
                }

                return strtoupper($row->map) === 'D'
                    ? $nominal
                    : -$nominal;
            })
            ->sum();
    }
}
