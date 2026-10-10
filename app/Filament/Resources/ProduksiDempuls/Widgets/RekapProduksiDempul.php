<?php

namespace App\Filament\Resources\ProduksiDempuls\Widgets;

use App\Models\DetailDempul;
use App\Models\ProduksiDempul;
use App\Models\RencanaPegawaiDempul;
use App\Services\ProductionAccessService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RekapProduksiDempul extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-dempuls.widgets.rekap-produksi-dempul';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal_produksi' => now()->toDateString(),
            'shift'            => 'pagi',
        ]);
        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal_produksi')
                    ->label('Pilih Tanggal Produksi')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn() => $this->loadData())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary'),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'pagi'  => 'Shift Pagi',
                        'malam' => 'Shift Malam',
                    ])
                    ->live()
                    ->afterStateUpdated(fn() => $this->loadData()),
            ])
            ->columns([
                'default' => 1,
                'sm'      => 2,
            ])
            ->statePath('data');
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $shift   = $this->data['shift'] ?? 'pagi';

        $kolomTanggal = ProduksiDempul::kolomTanggalAktif();

        $produksi = ProductionAccessService::applyDateRestriction(
            ProduksiDempul::query(),
            $kolomTanggal,
            7
        )
            ->whereDate($kolomTanggal, $tanggal)
            ->where('shift', $shift)
            ->first();

        if (! $produksi) {
            $this->summary = ['ada_data' => false];
            return;
        }

        $produksiId = $produksi->id;

        // ===== Total modal & hasil =====
        $totalAll = DetailDempul::where('id_produksi_dempul', $produksiId)
            ->sum(DB::raw('CAST(hasil AS UNSIGNED)'));

        $totalModal = DetailDempul::where('id_produksi_dempul', $produksiId)
            ->sum(DB::raw('CAST(modal AS UNSIGNED)'));

        // ===== Rincian per ukuran + kw =====
        $rows = DetailDempul::query()
            ->where('detail_dempuls.id_produksi_dempul', $produksiId)
            ->join('barang_setengah_jadi_hp as bsj', 'bsj.id', '=', 'detail_dempuls.id_barang_setengah_jadi_hp')
            ->join('ukurans', 'ukurans.id', '=', 'bsj.id_ukuran')
            ->join('grades', 'grades.id', '=', 'bsj.id_grade')
            ->join('kategori_barang', 'kategori_barang.id', '=', 'grades.id_kategori_barang')
            ->selectRaw('
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                CONCAT(kategori_barang.nama_kategori, " ", grades.nama_grade) AS kw,
                SUM(CAST(detail_dempuls.modal AS UNSIGNED)) AS modal,
                SUM(CAST(detail_dempuls.hasil AS UNSIGNED)) AS total
            ')
            ->groupBy('ukuran', 'kategori_barang.nama_kategori', 'grades.nama_grade')
            ->orderBy('ukuran')
            ->get();

        // ===== Pekerja: gabungan rencana pegawai + pegawai di tiap detail =====
        $dariRencana = RencanaPegawaiDempul::query()
            ->where('id_produksi_dempul', $produksiId)
            ->whereNotNull('id_pegawai')
            ->with('pegawai')
            ->get()
            ->map(fn($r) => $r->pegawai)
            ->filter();

        $dariDetail = DetailDempul::query()
            ->where('id_produksi_dempul', $produksiId)
            ->with('pegawais')
            ->get()
            ->flatMap(fn($d) => $d->pegawais);

        $pegawaiUnik = $dariRencana->concat($dariDetail)->unique('id')->values();

        $namaPegawai = $pegawaiUnik
            ->map(fn($p) => $p->nama_pegawai
                ?? $p->nama
                ?? $p->nama_lengkap
                ?? 'ID ' . $p->id)
            ->all();

        $this->summary = [
            'ada_data'     => true,
            'totalAll'     => (int) $totalAll,
            'totalModal'   => (int) $totalModal,
            'totalPegawai' => $pegawaiUnik->count(),
            'rows'         => $rows,
            'namaPegawai'  => $namaPegawai,
        ];
    }
}
