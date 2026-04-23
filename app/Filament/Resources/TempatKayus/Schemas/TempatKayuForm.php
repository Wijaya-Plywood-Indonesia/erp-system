<?php

namespace App\Filament\Resources\TempatKayus\Schemas;

use App\Models\KayuMasuk;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;

class TempatKayuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('jumlah_batang')
                    ->required()
                    ->numeric(),
                TextInput::make('kubikasi')
                    ->label('Kubikasi (m³)')
                    ->required()
                    ->numeric()
                    ->placeholder('0.0000'),

                Select::make('id_kayu_masuk')
                    ->label('Kayu Masuk')
                    ->options(function () {
                        return KayuMasuk::query()
                            ->get()
                            ->mapWithKeys(function ($kayuMasuk) {
                                return [
                                    $kayuMasuk->id => 'Seri - ' . $kayuMasuk->seri,
                                ];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Seri Kayu Masuk')
                    ->required(),
                // ->required(),
                Select::make('id_lahan')
                    ->label('Lahan')
                    ->relationship('lahan', 'kode_lahan')
                    ->searchable()
                    ->preload()
                    ->required()
            ]);
    }
}
