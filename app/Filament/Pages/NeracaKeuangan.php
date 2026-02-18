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
    public float $total_ekuitas = 0;

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

    // Reload hanya saat tanggal berubah
    public function updated($field)
    {
        if (in_array($field, ['tanggal_awal', 'tanggal_akhir'])) {
            $this->loadNeraca();
        }
    }

    // ================= APPLY FILTER =================
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

    // ================= LOAD DATA =================
    private function loadNeraca(): void
    {
        $rows = JurnalUmum::query()
            ->with([
                'subAkun.anakAkun.indukAkun'
            ])
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

            $data[$kodeInduk]['nama'] = $induk->nama_induk_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['nama'] = $anak->nama_anak_akun;
            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['nama']
                = $sub->nama_sub_anak_akun;

            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit']
                = ($data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['debit'] ?? 0)
                + $row->debit;

            $data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit']
                = ($data[$kodeInduk]['anak'][$kodeAnak]['sub'][$kodeSub]['kredit'] ?? 0)
                + $row->kredit;
        }

        ksort($data);

        return $data;
    }

    // ================= TOTAL =================
    private function hitungTotal(): void
    {
        $this->total_aset = 0; // kalau tidak dipakai boleh dihapus
        $this->total_kewajiban = 0;
        $this->total_ekuitas = 0;

        foreach ($this->neraca as $kodeInduk => $induk) {

            $saldoInduk = collect($induk['anak'] ?? [])
                ->flatMap(fn($anak) => $anak['sub'] ?? [])
                ->sum(fn($sub) => ($sub['debit'] ?? 0) - ($sub['kredit'] ?? 0));

            $kode = (int) $kodeInduk;

            // 1000–3000 → Ekuitas
            if ($kode >= 1000 && $kode <= 3999) {
                $this->total_ekuitas += $saldoInduk;
            }

            // 4000–6000 → Kewajiban
            if ($kode >= 4000 && $kode <= 6999) {
                $this->total_kewajiban += $saldoInduk;
            }
        }
    }
}
