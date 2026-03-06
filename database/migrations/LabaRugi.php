<?php

namespace App\Filament\Pages;

use App\Models\AkunGroup;
use App\Models\BukuBesar;
use App\Models\AnakAkun;
use App\Models\SubAnakAkun;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Form;
use BackedEnum;
use UnitEnum;

class LabaRugi extends Page
{
    // protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    // protected static ?string $navigationLabel = 'Laba Rugi';
    protected static ?string $title = 'Laporan Laba Rugi';

    protected string $view = 'filament.pages.laba-rugi';

    // Filter properties
    public ?int $tahun        = null;
    public ?int $bulan_dari   = null;
    public ?int $bulan_sampai = null;

    // Untuk form statePath('data')
    public array $data = [];

    // Hasil build laporan
    public array $laporanData = [];
    public bool  $sudahFilter = false;

    /*
    |--------------------------------------------------------------------------
    | LIFECYCLE
    |--------------------------------------------------------------------------
    */

    public function mount(): void
    {
        $this->tahun        = now()->year;
        $this->bulan_dari   = 1;
        $this->bulan_sampai = now()->month;

        $this->schema->fill([
            'tahun'        => $this->tahun,
            'bulan_dari'   => $this->bulan_dari,
            'bulan_sampai' => $this->bulan_sampai,
        ]);

        $this->generateLaporan();
    }

    /*
    |--------------------------------------------------------------------------
    | FORM FILTER
    |--------------------------------------------------------------------------
    */

    public function schema(Schema $schema): Schema
    {
        $bulanOptions = [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $tahunOptions = collect(range(now()->year, now()->year - 5))
            ->mapWithKeys(fn($y) => [$y => (string) $y])
            ->toArray();

        return $schema
            ->schema([
                Grid::make(3)->schema([
                    Select::make('tahun')
                        ->label('Tahun')
                        ->options($tahunOptions)
                        ->required()
                        ->live(),

                    Select::make('bulan_dari')
                        ->label('Dari Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),

                    Select::make('bulan_sampai')
                        ->label('Sampai Bulan')
                        ->options($bulanOptions)
                        ->required()
                        ->live(),
                ]),
            ])
            ->statePath('data');
    }

    /*
    |--------------------------------------------------------------------------
    | ACTION: FILTER SUBMIT
    |--------------------------------------------------------------------------
    */

    public function filter(): void
    {
        $this->validate([
            'data.tahun'        => 'required|integer',
            'data.bulan_dari'   => 'required|integer|min:1|max:12',
            'data.bulan_sampai' => 'required|integer|min:1|max:12',
        ]);

        $this->tahun        = (int) $this->data['tahun'];
        $this->bulan_dari   = (int) $this->data['bulan_dari'];
        $this->bulan_sampai = (int) $this->data['bulan_sampai'];

        $this->generateLaporan();
    }

    /*
    |--------------------------------------------------------------------------
    | CORE: BUILD LAPORAN
    |--------------------------------------------------------------------------
    */

    public function generateLaporan(): void
    {
        $saldoMap = $this->getSaldoMap();

        $rootGroups = AkunGroup::whereNull('parent_id')
            ->visible()
            ->ordered()
            ->with([
                'childrenRecursive.anakAkuns.subAnakAkuns',
                'anakAkuns.subAnakAkuns',
            ])
            ->get();

        $this->laporanData = $rootGroups->map(
            fn($group) => $this->buildGroupNode($group, $saldoMap)
        )->toArray();

        $this->sudahFilter = true;
    }

    /*
    |--------------------------------------------------------------------------
    | RECURSIVE NODE BUILDER
    |--------------------------------------------------------------------------
    */

    /**
     * Build node AkunGroup secara rekursif.
     *
     * Hierarki yang didukung:
     *   AkunGroup (root)
     *     └─ AkunGroup (child, bisa bertingkat)
     *         └─ AnakAkun (via pivot akun_group_anak_akun)
     *             └─ SubAnakAkun (via hasMany, opsional)
     */
    private function buildGroupNode(AkunGroup $group, array $saldoMap): array
    {
        $children   = [];
        $totalNilai = 0;

        // A. Proses child AkunGroup secara rekursif
        foreach ($group->children as $childGroup) {
            $node       = $this->buildGroupNode($childGroup, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['total_nilai'];
        }

        // B. Proses AnakAkun yang langsung di-assign ke group ini
        foreach ($group->anakAkuns as $anak) {
            $node       = $this->buildAnakAkunNode($anak, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['total_nilai'];
        }

        return [
            'type'        => 'group',
            'id'          => $group->id,
            'nama'        => $group->nama,
            'hidden'      => $group->hidden,
            'children'    => $children,
            'total_nilai' => $totalNilai,
        ];
    }

    /**
     * Build node AnakAkun.
     * - Jika punya SubAnakAkun → sub jadi children, total dari sub
     * - Jika tidak punya sub → ambil saldo langsung dari buku besar
     */
    private function buildAnakAkunNode(AnakAkun $anak, array $saldoMap): array
    {
        $children   = [];
        $totalNilai = 0;
        $nilaiAnak  = $saldoMap[$anak->kode_anak_akun] ?? null;

        foreach ($anak->subAnakAkuns as $sub) {
            $node       = $this->buildSubAnakAkunNode($sub, $saldoMap);
            $children[] = $node;
            $totalNilai += $node['nilai'];
        }

        if (empty($children)) {
            // Tidak punya sub → pakai saldo langsung
            $totalNilai = $nilaiAnak ?? 0;
        } else {
            // Punya sub → total dari sub (+ saldo anak kalau ada)
            if ($nilaiAnak !== null) {
                $totalNilai += $nilaiAnak;
            }
        }

        return [
            'type'        => 'anak_akun',
            'kode'        => $anak->kode_anak_akun,
            'nama'        => $anak->nama_anak_akun,
            'children'    => $children,
            'total_nilai' => $totalNilai,
            'nilai'       => $nilaiAnak ?? 0,
        ];
    }

    /**
     * Build node SubAnakAkun (selalu leaf, tidak punya children).
     */
    private function buildSubAnakAkunNode(SubAnakAkun $sub, array $saldoMap): array
    {
        return [
            'type'     => 'sub_anak_akun',
            'kode'     => $sub->kode_sub_anak_akun,
            'nama'     => $sub->nama_sub_anak_akun,
            'nilai'    => $saldoMap[$sub->kode_sub_anak_akun] ?? 0,
            'children' => [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Ambil saldo dari BukuBesar untuk periode filter.
     * Return: flat array [kode_akun => total_saldo]
     * Kode akun sudah unik antar AnakAkun & SubAnakAkun sehingga bisa disatukan.
     */
    private function getSaldoMap(): array
    {
        $rows = BukuBesar::where('tahun', $this->tahun)
            ->whereBetween('bulan', [$this->bulan_dari, $this->bulan_sampai])
            ->get(['no_akun', 'saldo']);

        $map = [];
        foreach ($rows as $row) {
            $kode = $row->no_akun;
            $map[$kode] = ($map[$kode] ?? 0) + (float) $row->saldo;
        }

        return $map;
    }

    /*
    |--------------------------------------------------------------------------
    | VIEW HELPERS
    |--------------------------------------------------------------------------
    */

    public function getNamaBulan(int $bulan): string
    {
        return [
            1  => 'Januari',
            2  => 'Februari',
            3  => 'Maret',
            4  => 'April',
            5  => 'Mei',
            6  => 'Juni',
            7  => 'Juli',
            8  => 'Agustus',
            9  => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ][$bulan] ?? '';
    }

    public function formatRupiah(float $nilai): string
    {
        return 'Rp ' . number_format(abs($nilai), 0, ',', '.');
    }
}
