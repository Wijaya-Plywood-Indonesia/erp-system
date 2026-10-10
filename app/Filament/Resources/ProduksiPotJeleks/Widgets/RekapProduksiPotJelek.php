<?php

namespace App\Filament\Resources\ProduksiPotJeleks\Widgets;

use App\Models\DetailBarangDikerjakanPotJelek;
use App\Models\Pegawai;
use App\Models\ProduksiPotJelek;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RekapProduksiPotJelek extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-pot-jeleks.widgets.rekap-produksi-pot-jelek';
    protected int|string|array $columnSpan = 'full';

    public ?array $data = [];
    public array $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'tanggal_produksi' => now()->toDateString(),
        ]);

        $this->loadData();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                DatePicker::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->live()
                    ->closeOnDateSelection()
                    ->afterStateUpdated(fn() => $this->loadData())
                    ->suffixIcon('heroicon-o-calendar')
                    ->suffixIconColor('primary')
                    ->columnSpan('full'),
            ])
            ->columns(1)
            ->statePath('data');
    }

    protected function kolomTanggal(): string
    {
        return 'tanggal_produksi'; // Sesuaikan menjadi 'tgl_produksi' jika kolom di database Anda menggunakan nama tersebut
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        // Ambil semua ID produksi pot jelek berdasarkan tanggal
        $produksiIds = ProduksiPotJelek::query()
            ->whereDate($kolom, $tanggal)
            ->pluck('id');

        if ($produksiIds->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== 1. TOTAL PRODUKSI (TINGGI) =====
        $totalAll = DetailBarangDikerjakanPotJelek::whereIn('id_produksi_pot_jelek', $produksiIds)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // ===== 2. TOTAL PEGAWAI (UNIK) =====
        $idPegawai = DB::table('pegawai_pot_jelek')
            ->whereIn('id_produksi_pot_jelek', $produksiIds)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        $namaPegawai = Pegawai::whereIn('id', $idPegawai)
            ->get()
            ->map(fn($p) => $p->nama_pegawai ?? $p->nama ?? $p->nama_lengkap ?? 'ID ' . $p->id)
            ->values()
            ->all();

        // ===== 3. GLOBAL JENIS KAYU, UKURAN & KW =====
        $globalJenisKayuUkuran = DetailBarangDikerjakanPotJelek::query()
            ->whereIn('id_produksi_pot_jelek', $produksiIds)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_jelek.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_barang_dikerjakan_pot_jelek.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_barang_dikerjakan_pot_jelek.kw as kw,
                SUM(CAST(detail_barang_dikerjakan_pot_jelek.tinggi AS UNSIGNED)) AS total
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_barang_dikerjakan_pot_jelek.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'ada_data'              => true,
            'totalAll'              => (int) $totalAll,
            'totalPegawai'          => (int) $idPegawai->count(),
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'namaPegawai'           => $namaPegawai,
        ];
    }
}
