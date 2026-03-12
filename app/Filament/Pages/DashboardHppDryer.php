<?php

namespace App\Filament\Pages;

use App\Models\OngkosProduksiDryer;
use App\Models\StokVeneerKering;
use App\Services\HppDryerService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DashboardHppDryer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'HPP & Biaya';
    protected static ?string $navigationLabel = 'Dashboard HPP';
    protected static ?string $title = 'Dashboard HPP Veneer Kering';
    protected static ?int $navigationSort = 0;
    protected string $view = 'filament.pages.dashboard-hpp-dryer';

    public function getStats(): array
    {
        $stokAktif = StokVeneerKering::whereIn(
            'id',
            fn($q) => $q
                ->selectRaw('MAX(id)')
                ->from('stok_veneer_kerings')
                ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
        )->where('stok_m3_sesudah', '>', 0)->get();

        $totalM3 = (float) $stokAktif->sum('stok_m3_sesudah');
        $totalNilai = (float) $stokAktif->sum('nilai_stok_sesudah');
        $avgHpp = $totalM3 > 0 ? $totalNilai / $totalM3 : 0;

        $sesiHariIni = OngkosProduksiDryer::whereHas(
            'produksi',
            fn($q) => $q->whereDate('tanggal_produksi', today())
        )->count();

        return [
            [
                'label' => 'Total Stok Aktif',
                'value' => number_format($totalM3, 4) . ' m³',
                'description' => $stokAktif->count() . ' kombinasi produk',
            ],
            [
                'label' => 'Nilai Stok',
                'value' => 'Rp ' . number_format($totalNilai, 0, ',', '.'),
                'description' => 'HPP Avg: Rp ' . number_format($avgHpp, 0, ',', '.') . '/m³',
            ],
            [
                'label' => 'HPP Veneer Basah',
                'value' => 'Rp ' . number_format(HppDryerService::HPP_VENEER_BASAH_PER_M3, 0, ',', '.') . '/m³',
                'description' => 'Nilai sementara (placeholder)',
            ],
            [
                'label' => 'Produksi Hari Ini',
                'value' => $sesiHariIni . ' sesi',
                'description' => today()->translatedFormat('d F Y'),
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                StokVeneerKering::whereIn(
                    'id',
                    fn($q) => $q
                        ->selectRaw('MAX(id)')
                        ->from('stok_veneer_kerings')
                        ->groupBy('id_ukuran', 'id_jenis_kayu', 'kw')
                )->where('stok_m3_sesudah', '>', 0)->with(['ukuran', 'jenisKayu'])
            )
            ->heading('Rekap Stok Veneer Kering Aktif')
            ->columns([
                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),
                TextColumn::make('kw')
                    ->label('KW')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('stok_m3_sesudah')
                    ->label('Stok (M3)')
                    ->numeric(decimalPlaces: 4)
                    ->suffix(' m³')
                    ->sortable(),
                TextColumn::make('hpp_average')
                    ->label('HPP Average / M3')
                    ->money('IDR')
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('nilai_stok_sesudah')
                    ->label('Nilai Stok')
                    ->money('IDR')
                    ->weight(FontWeight::Bold)
                    ->sortable(),
                TextColumn::make('tanggal_transaksi')
                    ->label('Update Terakhir')
                    ->date('d/m/Y'),
            ]);
    }
}