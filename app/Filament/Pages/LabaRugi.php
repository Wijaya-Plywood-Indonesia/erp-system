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
    public $bulanAwal;
    public $bulanAkhir;
    public $tahun;
    public $periodeBulanan = [];
    public $modeMultiPeriode = false;
    public $totalPendapatanBulanan = [];
public $totalBiayaBulanan = [];
public $labaBersihBulanan = [];
    public $dataBulanan = [];
    public $hppBulanan = [];
public $pajakBulanan = [];
public $labaKotorBulanan = [];
public $labaSebelumPajakBulanan = [];
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
    public $akunMapping = [];

    public function mount()
    {
        $this->tahun = now()->year;
        $this->bulanAwal = now()->month;
        $this->bulanAkhir = now()->month;

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

    public function terapkanPeriode()
    {
        $this->modeMultiPeriode = true;

        $this->generatePeriode();
        $this->hitungMultiBulan();
    }
    public function kembaliDefault()
    {
        $this->modeMultiPeriode = false;

        $this->resetData();
        $this->hitung();
    }
    public function updated($property)
    {
        if (in_array($property, [
            'useCustomFilter',
            'tanggalAwal',
            'tanggalAkhir',
            'selectedAkun',
            'akunMapping',
            'bulanAwal',
            'bulanAkhir',
            'tahun'
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

    public function updatedAkunMapping()
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

        if ($this->bulanAwal && $this->bulanAkhir && $this->tahun) {

            $start = \Carbon\Carbon::create(
                $this->tahun,
                $this->bulanAwal,
                1
            )->startOfMonth();

            $end = \Carbon\Carbon::create(
                $this->tahun,
                $this->bulanAkhir,
                1
            )->endOfMonth();

            $query->whereBetween('tgl', [$start, $end]);
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
        // ================= REKLASIFIKASI USER =================
        if (!empty($this->akunMapping)) {

            foreach ($this->akunMapping as $kode => $section) {

                if (!$section) continue;

                $total = $this->sumFromJurnalUmum($kode);

                $nama = AnakAkun::where('kode_anak_akun', $kode)
                    ->value('nama_anak_akun');

                // HAPUS DARI AKUN LAINNYA
                $this->akunLainnya = array_filter(
                    $this->akunLainnya,
                    fn($item) => $item['kode'] != $kode
                );

                if ($section === 'pendapatan') {

                    $this->akunPendapatan[] = [
                        'kode' => $kode,
                        'nama' => $nama,
                        'total' => $total,
                    ];

                    $this->totalPendapatan += $total;
                }

                if ($section === 'biaya') {

                    $this->akunBiaya[] = [
                        'kode' => $kode,
                        'nama' => $nama,
                        'total' => $total,
                    ];

                    $this->totalBiaya += $total;
                }
            }
        }
        $this->pendapatanKotor = $this->totalPendapatan + $this->hpp;
        $this->pendapatanSebelumPajak = $this->pendapatanKotor + $this->totalBiaya;
        $this->labaBersih = $this->pendapatanSebelumPajak + $this->bebanPajak;
    }

    private function generatePeriode()
    {
        $this->periodeBulanan = [];

        if (!$this->bulanAwal || !$this->bulanAkhir) {
            return;
        }

        for ($i = $this->bulanAwal; $i <= $this->bulanAkhir; $i++) {
            $this->periodeBulanan[] = $i;
        }
    }

private function hitungMultiBulan()
{
    $this->dataBulanan = [];
    $this->totalPendapatanBulanan = [];
    $this->totalBiayaBulanan = [];
    $this->hppBulanan = [];
    $this->pajakBulanan = [];
    $this->labaKotorBulanan = [];
    $this->labaSebelumPajakBulanan = [];
    $this->labaBersihBulanan = [];

    foreach ($this->periodeBulanan as $bulan) {

        $start = \Carbon\Carbon::create($this->tahun, $bulan, 1)->startOfMonth();
        $end   = \Carbon\Carbon::create($this->tahun, $bulan, 1)->endOfMonth();

        $query = JurnalUmum::whereBetween('tgl', [$start, $end])->get();

        foreach ($query as $row) {

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
            $akunRatusan = floor($akunPuluhan / 100) * 100;

            // simpan per akun
            $this->dataBulanan[$akunRatusan][$bulan] =
                ($this->dataBulanan[$akunRatusan][$bulan] ?? 0) + $signed;

            // ================= PENDAPATAN =================
            if ($akunRatusan >= 4000 && $akunRatusan < 5000) {
                $this->totalPendapatanBulanan[$bulan] =
                    ($this->totalPendapatanBulanan[$bulan] ?? 0) + $signed;
            }

            // ================= BIAYA =================
            if ($akunRatusan >= 5000 && $akunRatusan < 6000 && $akunRatusan != 5900) {
                $this->totalBiayaBulanan[$bulan] =
                    ($this->totalBiayaBulanan[$bulan] ?? 0) + $signed;
            }

            // ================= PAJAK =================
            if ($akunRatusan == 5900) {
                $this->pajakBulanan[$bulan] =
                    ($this->pajakBulanan[$bulan] ?? 0) + $signed;
            }

            // ================= HPP =================
            if (str_contains(strtolower($row->nama_akun ?? ''), 'hpp')) {
                $this->hppBulanan[$bulan] =
                    ($this->hppBulanan[$bulan] ?? 0) + $signed;
            }
        }

        // ======= RUMUS SAMA PERSIS DENGAN DEFAULT =======

        $this->labaKotorBulanan[$bulan] =
            ($this->totalPendapatanBulanan[$bulan] ?? 0)
            + ($this->hppBulanan[$bulan] ?? 0);

        $this->labaSebelumPajakBulanan[$bulan] =
            $this->labaKotorBulanan[$bulan]
            + ($this->totalBiayaBulanan[$bulan] ?? 0);

        $this->labaBersihBulanan[$bulan] =
            $this->labaSebelumPajakBulanan[$bulan]
            + ($this->pajakBulanan[$bulan] ?? 0);
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
