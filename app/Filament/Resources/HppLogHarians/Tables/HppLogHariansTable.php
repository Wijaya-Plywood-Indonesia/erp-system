<?php

namespace App\Filament\Resources\HppLogHarians\Tables;

use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HppLogHariansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(),
                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable(),
                TextColumn::make('kw')
                    ->label('KW')
                    ->badge()
                    ->alignCenter(),
                TextColumn::make('total_m3_masuk')
                    ->label('Masuk (M3)')
                    ->numeric(decimalPlaces: 4)
                    ->color('success'),
                TextColumn::make('total_m3_keluar')
                    ->label('Keluar (M3)')
                    ->numeric(decimalPlaces: 4)
                    ->color('danger'),
                TextColumn::make('stok_akhir_m3')
                    ->label('Stok Akhir (M3)')
                    ->numeric(decimalPlaces: 4)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('avg_ongkos_dryer_per_m3')
                    ->label('Ongkos Dryer/M3')
                    ->money('IDR'),
                TextColumn::make('hpp_kering_per_m3')
                    ->label('HPP Kering/M3')
                    ->money('IDR')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('hpp_average')
                    ->label('HPP Average/M3')
                    ->money('IDR')
                    ->weight(FontWeight::Bold)
                    ->color('primary'),
                TextColumn::make('nilai_stok_akhir')
                    ->label('Nilai Stok Akhir')
                    ->money('IDR'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                SelectFilter::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu'),
                Filter::make('bulan_ini')
                    ->label('Bulan Ini')
                    ->query(
                        fn(Builder $q) => $q
                            ->whereMonth('tanggal', now()->month)
                            ->whereYear('tanggal', now()->year)
                    ),
                Filter::make('rentang_tanggal')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('dari')->label('Dari'),
                        DatePicker::make('sampai')->label('Sampai'),
                    ])
                    ->query(
                        fn(Builder $q, array $data) => $q
                            ->when($data['dari'], fn($s) => $s->whereDate('tanggal', '>=', $data['dari']))
                            ->when($data['sampai'], fn($s) => $s->whereDate('tanggal', '<=', $data['sampai']))
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}