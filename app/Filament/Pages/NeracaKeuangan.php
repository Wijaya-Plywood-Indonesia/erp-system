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

    // ================= FILTER =================
    public ?string $tanggal_awal = null;
    public ?string $tanggal_akhir = null;

    public array $filter_induk = [];
    public array $filter_induk_temp = [];

    // ================= DATA =================
    public array $neraca = [];

    public float $total_aset = 0;
    public float $total_kewajiban = 0;
    public float $total_modal = 0;

    public float $total_pendapatan = 0;
    public float $total_beban = 0;
    public float $total_hpp = 0;

    public array $listInduk = [];

    // ================= AUTH =================
    public static function canView(): bool
    {
        return auth()->user()->can('view_neraca_keuangan');
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'neraca-keuangan';
    }

    // ================= INIT =================
    public function mount(): void
    {
        $this->tanggal_awal = now()->startOfMonth()->toDateString();
        $this->tanggal_akhir = now()->endOfMonth()->toDateString();

        $this->listInduk = IndukAkun::pluck(
            'nama_induk_akun',
            'kode_induk_akun'
        )->toArray();

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

    // ================= LOAD =================
    private function loadNeraca(): void
    {
        $rows = JurnalUmum::query()
            ->with(['subAkun.anakAkun.indukAkun'])
            ->whereBetween('tgl', [$this->tanggal_awal, $this->tanggal_akhir])
            ->when(!empty($this->filter_induk), function ($query) {
                $query->whereHas(
                    'subAkun.anakAkun.indukAkun',
                    fn($q) =>
                    $q->whereIn('kode_induk_akun', $this->filter_induk)
                );
            })
            ->get();

        $this->neraca = $this->groupData($rows);
        $this->hitungTotal();
    }

    // ================= HITUNG NILAI =================
    private function hitungNilai($row): float
    {
        $harga = $row->harga ?? 0;

        return match ($row->hit_kbk) {
            'm' => $harga * ($row->m3 ?? 0),
            'b' => $harga * ($row->banyak ?? 0),
            default => $harga,
        };
    }

    // ================= GROUPING =================
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

            // INIT STRUKTUR
            $data[$kodeInduk]['nama'] ??= $induk->nama_induk_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['nama'] ??= $anak->nama_anak_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['nama']
                = $sub->nama_sub_anak_akun;

            // INIT DEFAULT
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'] ??= 0;
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'] ??= 0;
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['saldo'] ??= 0;
            $data[$kodeInduk]['anak'][$kodeAnak]['saldo'] ??= 0;
            $data[$kodeInduk]['saldo'] ??= 0;

            // ISI DEBIT / KREDIT
            if ($row->debit > 0) {
                $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'] += $nilai;
            }

            if ($row->kredit > 0) {
                $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'] += $nilai;
            }

            // HITUNG SALDO PER SUB
            $kodeInt = (int) $kodeInduk;

            $debit = $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'];
            $kredit = $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'];

            $saldoSub = $this->hitungSaldoByJenis($kodeInt, $debit, $kredit);

            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['saldo'] = $saldoSub;
        }

        /*
        |--------------------------------------------------------------------------
        | 🔥 HITUNG TOTAL ANAK & INDUK
        |--------------------------------------------------------------------------
        */

        foreach ($data as $kodeInduk => &$induk) {

            foreach ($induk['anak'] as $kodeAnak => &$anak) {

                $anak['saldo'] = collect($anak['sub'])
                    ->sum(fn($sub) => $sub['saldo'] ?? 0);
            }

            $induk['saldo'] = collect($induk['anak'])
                ->sum(fn($anak) => $anak['saldo'] ?? 0);
        }

        ksort($data);

        return $data;
    }

    // ================= SALDO LOGIC =================
    private function hitungSaldoByJenis(int $kode, float $debit, float $kredit): float
    {
        // Semua akun pakai pola debit - kredit
        return $debit - $kredit;
    }

    // ================= TOTAL =================
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

            $debit = collect($induk['anak'] ?? [])
                ->flatMap(fn($anak) => $anak['sub'] ?? [])
                ->sum(fn($sub) => $sub['debit'] ?? 0);

            $kredit = collect($induk['anak'] ?? [])
                ->flatMap(fn($anak) => $anak['sub'] ?? [])
                ->sum(fn($sub) => $sub['kredit'] ?? 0);

            $saldo = $this->hitungSaldoByJenis($kode, $debit, $kredit);

            if ($kode >= 1000 && $kode <= 1999) {
                $this->total_aset += $saldo;
            }

            if ($kode >= 2000 && $kode <= 2999) {
                $this->total_kewajiban += $saldo;
            }

            if ($kode >= 3000 && $kode <= 3999) {
                $this->total_modal += $saldo;
            }

            if ($kode >= 4000 && $kode <= 4999) {
                $this->total_pendapatan += $saldo;
            }

            if ($kode >= 5000 && $kode <= 5999) {
                $this->total_beban += $saldo;
            }

            if ($kode >= 6000 && $kode <= 6999) {
                $this->total_hpp += $saldo;
            }
        }
    }
}
