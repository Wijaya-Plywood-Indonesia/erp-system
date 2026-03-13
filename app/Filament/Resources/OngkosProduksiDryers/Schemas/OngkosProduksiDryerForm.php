<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Schemas;

use App\Services\HppDryerService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
// use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class OngkosProduksiDryerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Sesi Produksi')
                    ->schema([
                        Select::make('id_produksi_dryer')
                            ->label('Sesi Produksi')
                            ->relationship(
                                'produksi',
                                'id',
                                fn(Builder $q) => $q->orderByDesc('tanggal_produksi')
                            )
                            ->getOptionLabelFromRecordUsing(fn($r) => $r->label)
                            ->searchable()
                            ->required()
                            ->disabled(fn($record) => $record?->is_final),
                        TextInput::make('total_m3')
                            ->label('Total M3')
                            ->numeric()
                            ->disabled()
                            ->suffix('m³')
                            ->helperText('Dihitung otomatis dari detail hasil produksi'),
                    ])->columns(2),

                Section::make('Pekerja & Mesin')
                    ->schema([
                        TextInput::make('ttl_pekerja')
                            ->label('Jumlah Pekerja Hadir')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->disabled(fn($record) => $record?->is_final),
                        TextInput::make('jumlah_mesin')
                            ->label('Jumlah Mesin Digunakan')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->disabled(fn($record) => $record?->is_final),
                    ])->columns(2),

                Section::make('Tarif')
                    ->description('Ubah hanya jika ada penyesuaian dari tarif standar.')
                    ->schema([
                        TextInput::make('tarif_per_pekerja')
                            ->label('Tarif / Pekerja')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(115_000)
                            ->required()
                            ->disabled(fn($record) => $record?->is_final),
                        TextInput::make('tarif_per_mesin')
                            ->label('Tarif / Mesin')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(335_000)
                            ->required()
                            ->disabled(fn($record) => $record?->is_final),
                    ])->columns(2),

                Section::make('Hasil Kalkulasi')
                    ->schema([
                        Placeholder::make('ongkos_pekerja')
                            ->label('Ongkos Pekerja')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format($record->ongkos_pekerja, 0, ',', '.')
                                : '-'),
                        Placeholder::make('ongkos_mesin')
                            ->label('Ongkos Mesin')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format($record->ongkos_mesin, 0, ',', '.')
                                : '-'),
                        Placeholder::make('total_ongkos')
                            ->label('Total Ongkos')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format($record->total_ongkos, 0, ',', '.')
                                : '-'),
                        Placeholder::make('ongkos_per_m3')
                            ->label('Ongkos / M3')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format($record->ongkos_per_m3, 0, ',', '.')
                                : '-'),
                        Placeholder::make('hpp_kering_per_m3')
                            ->label('HPP Veneer Kering / M3')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format(
                                    HppDryerService::HPP_VENEER_BASAH_PER_M3 + $record->ongkos_per_m3,
                                    0,
                                    ',',
                                    '.'
                                )
                                : '-')
                            ->helperText('= HPP Basah (Rp 1.000.000) + Ongkos Dryer'),
                    ])->columns(3)->visibleOn(['view', 'edit']),

                Toggle::make('is_final')
                    ->label('Kunci Data (Final)')
                    ->helperText('Setelah dikunci, tarif tidak bisa diubah.')
                    ->visible(fn($record) => $record !== null),
            ]);
    }
}