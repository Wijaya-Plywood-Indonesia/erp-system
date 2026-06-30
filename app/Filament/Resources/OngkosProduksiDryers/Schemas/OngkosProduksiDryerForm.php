<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Placeholder;

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
                            ->options(function () {
                                $sudahAda = \App\Models\OngkosProduksiDryer::pluck('id_produksi_dryer')->toArray();

                                return \App\Models\ProduksiPressDryer::orderByDesc('tanggal_produksi')
                                    ->whereNotIn('id', $sudahAda)
                                    ->get()
                                    ->mapWithKeys(fn($p) => [
                                        $p->id => $p->tanggal_produksi->format('d/m/Y') . ' | ' . $p->shift
                                    ]);
                            })
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
                            ->label('Ongkos Dryer / M3')
                            ->content(fn($record) => $record
                                ? 'Rp ' . number_format($record->ongkos_per_m3, 0, ',', '.')
                                : '-')
                            ->helperText('= (Ongkos Pekerja + Ongkos Mesin) / Total M3'),
                    ])->columns(2)->visibleOn(['view', 'edit']),

                Toggle::make('is_final')
                    ->label('Kunci Data (Final)')
                    ->helperText('Setelah dikunci, data tidak bisa diubah.')
                    ->visible(fn($record) => $record !== null),
            ]);
    }
}