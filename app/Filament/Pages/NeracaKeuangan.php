<?php

namespace App\Filament\Pages;

use App\Models\JurnalUmum;
use App\Models\IndukAkun;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class NeracaKeuangan extends Page
{
    protected string $view = 'filament.pages.neraca-keuangan';

    protected static ?string $navigationLabel = 'Neraca Keuangan';
    protected static ?string $title = 'Neraca Keuangan';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-scale';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    // ============ STATE =============
    public ?string $tanggal_awal = null;
    public ?string $tanggal_akhir = null;

    public array $filter_induk = [];
    public array $filter_induk_temp = [];

    public array $neraca = [];

    public float $total_aset = 0;
    public float $total_kewajiban = 0;
    public float $total_modal = 0;
    public float $total_pendapatan = 0;
    public float $total_beban = 0;
    public float $total_hpp = 0;

    public array $listInduk = [];

    // ============ AUTH ============
    public static function canView(): bool
    {
        return auth()->user()->can('view_neraca_keuangan');
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'neraca-keuangan';
    }

    // ============ INIT ============
    public function mount(): void
    {
        $this->tanggal_awal = now()->startOfMonth()->toDateString();
        $this->tanggal_akhir = now()->endOfMonth()->toDateString();

        $this->listInduk = IndukAkun::pluck('nama_induk_akun', 'kode_induk_akun')
            ->toArray();

        $this->loadNeraca();
    }

    public function updated($field)
    {
        if (in_array($field, ['tanggal_awal', 'tanggal_akhir'])) {
            $this->loadNeraca();
        }
    }

    public function applyFilter()
    {
        $this->filter_induk = $this->filter_induk_temp;
        $this->loadNeraca();
    }

    public function resetFilter()
    {
        $this->filter_induk = [];
        $this->filter_induk_temp = [];
        $this->loadNeraca();
    }

    // ============ CORE LOAD ============
    private function loadNeraca(): void
    {
        $rows = JurnalUmum::query()
            ->with(['subAkun.anakAkun.indukAkun'])
            ->whereBetween('tgl', [$this->tanggal_awal, $this->tanggal_akhir])
            ->when(
                !empty($this->filter_induk),
                fn($q) =>
                $q->whereHas(
                    'subAkun.anakAkun.indukAkun',
                    fn($x) => $x->whereIn('kode_induk_akun', $this->filter_induk)
                )
            )
            ->get();

        $this->neraca = $this->groupData($rows);
        $this->hitungTotal();
    }

    // ============ HITUNG NILAI TRANSAKSI ============
    private function hitungNilai($row): float
    {
        $harga = $row->harga ?? 0;

        return match ($row->hit_kbk) {
            'm' => $harga * ($row->m3 ?? 0),
            'b' => $harga * ($row->banyak ?? 0),
            default => $harga,
        };
    }

    // ============ GROUPING =============
    private function groupData($rows): array
    {
        $data = [];

        foreach ($rows as $row) {

            $induk = $row->subAkun?->anakAkun?->indukAkun;
            $anak = $row->subAkun?->anakAkun;
            $sub = $row->subAkun;

            if (!$induk || !$anak || !$sub) {
                continue;
            }

            $kodeInduk = $induk->kode_induk_akun;
            $kodeAnak = $anak->kode_anak_akun;
            $kodeSub = $sub->kode_sub_anak_akun;

            $nilai = $this->hitungNilai($row);

            // Init struktur
            $data[$kodeInduk]['nama'] ??= $induk->nama_induk_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['nama'] ??= $anak->nama_anak_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub] ??= [
                'nama' => $sub->nama_sub_anak_akun,
                'debit' => 0,
                'kredit' => 0,
                'saldo' => 0,
            ];

            // Tambah debit/kredit
            if ($row->debit > 0) {
                $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'] += $nilai;
            }

            if ($row->kredit > 0) {
                $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'] += $nilai;
            }

            // Hitung saldo sub
            $d = $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'];
            $k = $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'];

            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['saldo'] =
                $this->hitungSaldoByJenis($kodeInduk, $d, $k);
        }

        // Hitung total anak & induk
        foreach ($data as $kodeInduk => &$induk) {

            foreach ($induk['anak'] as $kodeAnak => &$anak) {
                $anak['saldo'] = collect($anak['sub'])->sum('saldo');
            }

            $induk['saldo'] = collect($induk['anak'])->sum('saldo');
        }

        ksort($data);

        return $data;
    }

    // ============ SALDO LOGIC — STANDAR AKUNTANSI ============
    private function hitungSaldoByJenis(int|string $kode, float $debit, float $kredit): float
    {
        $kode = (int) $kode;

        return match (true) {
            // Aset
            $kode >= 1000 && $kode <= 1999 => $debit - $kredit,

            // Kewajiban, Modal, Pendapatan
            $kode >= 2000 && $kode <= 4999 => $kredit - $debit,

            // Beban + HPP
            $kode >= 5000 && $kode <= 6999 => $debit - $kredit,

            default => $debit - $kredit,
        };
    }

    // ============ TOTAL =============
    private function hitungTotal(): void
    {
        $this->total_aset = 0;
        $this->total_kewajiban = 0;
        $this->total_modal = 0;
        $this->total_pendapatan = 0;
        $this->total_beban = 0;
        $this->total_hpp = 0;

        foreach ($this->neraca as $kodeInduk => $induk) {

            $kode = (int) $kodeInduk;
            $saldo = $induk['saldo'] ?? 0;

            if ($kode >= 1000 && $kode <= 1999)
                $this->total_aset += $saldo;
            if ($kode >= 2000 && $kode <= 2999)
                $this->total_kewajiban += $saldo;
            if ($kode >= 3000 && $kode <= 3999)
                $this->total_modal += $saldo;
            if ($kode >= 4000 && $kode <= 4999)
                $this->total_pendapatan += $saldo;
            if ($kode >= 5000 && $kode <= 5999)
                $this->total_beban += $saldo;
            if ($kode >= 6000 && $kode <= 6999)
                $this->total_hpp += $saldo;
        }
    }
}

