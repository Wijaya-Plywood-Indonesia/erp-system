<?php

namespace App\Filament\Resources\ProduksiPotSikus\Widgets;

use App\Models\DetailBarangDikerjakanPotSiku;
use App\Models\Pegawai;
use App\Models\ProduksiPotSiku;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;

class RekapProduksiPotSiku extends Widget implements HasSchemas
{
    use InteractsWithSchemas, HasWidgetShield;

    protected string $view = 'filament.resources.produksi-pot-sikus.widgets.rekap-produksi-pot-siku';
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
                    ->columnSpan('full'), // Memenuhi lebar form karena shift dihilangkan
            ])
            ->columns(1)
            ->statePath('data');
    }

    protected function kolomTanggal(): string
    {
        return 'tanggal_produksi';
    }

    public function loadData(): void
    {
        $tanggal = Carbon::parse($this->data['tanggal_produksi'] ?? now())->toDateString();
        $kolom   = $this->kolomTanggal();

        // Ambil semua ID produksi pot siku berdasarkan tanggal
        $produksiIds = ProduksiPotSiku::query()
            ->whereDate($kolom, $tanggal)
            ->pluck('id');

        if ($produksiIds->isEmpty()) {
            $this->summary = ['ada_data' => false];
            return;
        }

        // ===== 1. TOTAL PRODUKSI (TINGGI) =====
        $totalAll = DetailBarangDikerjakanPotSiku::whereIn('id_produksi_pot_siku', $produksiIds)
            ->sum(DB::raw('CAST(tinggi AS UNSIGNED)'));

        // ===== 2. TOTAL VOLUME M3 =====
        $totalVolumeM3 = DetailBarangDikerjakanPotSiku::query()
            ->whereIn('id_produksi_pot_siku', $produksiIds)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->sum(DB::raw('
                (CAST(ukurans.panjang AS DECIMAL(10,2)) * CAST(ukurans.lebar AS DECIMAL(10,2)) * CAST(ukurans.tebal AS DECIMAL(10,2)) * CAST(detail_barang_dikerjakan_pot_siku.tinggi AS DECIMAL(10,2))) / 1000000
            '));

        // ===== 3. TOTAL PEGAWAI (UNIK) =====
        $idPegawai = DB::table('pegawai_pot_siku')
            ->whereIn('id_produksi_pot_siku', $produksiIds)
            ->whereNotNull('id_pegawai')
            ->pluck('id_pegawai')
            ->unique()
            ->values();

        $namaPegawai = Pegawai::whereIn('id', $idPegawai)
            ->get()
            ->map(fn($p) => $p->nama_pegawai ?? $p->nama ?? $p->nama_lengkap ?? 'ID ' . $p->id)
            ->values()
            ->all();

        // ===== 4. RINCIAN JENIS KAYU & UKURAN + KW =====
        $globalJenisKayuUkuran = DetailBarangDikerjakanPotSiku::query()
            ->whereIn('id_produksi_pot_siku', $produksiIds)
            ->join('ukurans', 'ukurans.id', '=', 'detail_barang_dikerjakan_pot_siku.id_ukuran')
            ->join('jenis_kayus', 'jenis_kayus.id', '=', 'detail_barang_dikerjakan_pot_siku.id_jenis_kayu')
            ->selectRaw('
                jenis_kayus.nama_kayu as jenis_kayu,
                CONCAT(
                    TRIM(TRAILING ".00" FROM CAST(ukurans.panjang AS CHAR)), " x ",
                    TRIM(TRAILING ".00" FROM CAST(ukurans.lebar AS CHAR)), " x ",
                    TRIM(TRAILING "." FROM TRIM(TRAILING "0" FROM CAST(ukurans.tebal AS CHAR)))
                ) AS ukuran,
                detail_barang_dikerjakan_pot_siku.kw as kw,
                SUM(CAST(detail_barang_dikerjakan_pot_siku.tinggi AS UNSIGNED)) AS total,
                SUM(
                    (CAST(ukurans.panjang AS DECIMAL(10,2)) * CAST(ukurans.lebar AS DECIMAL(10,2)) * CAST(ukurans.tebal AS DECIMAL(10,2)) * CAST(detail_barang_dikerjakan_pot_siku.tinggi AS DECIMAL(10,2))) / 1000000
                ) AS total_m³
            ')
            ->groupBy('jenis_kayus.nama_kayu', 'ukuran', 'detail_barang_dikerjakan_pot_siku.kw')
            ->orderBy('jenis_kayus.nama_kayu')
            ->orderBy('ukuran')
            ->get();

        $this->summary = [
            'ada_data'              => true,
            'totalAll'              => (int) $totalAll,
            'totalVolumeM3'         => $totalVolumeM3,
            'totalPegawai'          => (int) $idPegawai->count(),
            'globalJenisKayuUkuran' => $globalJenisKayuUkuran,
            'namaPegawai'           => $namaPegawai,
        ];
    }
}
