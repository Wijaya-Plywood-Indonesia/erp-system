<?php

namespace App\Filament\Resources\HargaKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HargaKayuInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detail Kayu')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('panjang')
                                    ->label('Panjang (cm)')
                                    ->numeric(),

                                TextEntry::make('harga_beli')
                                    ->label('Harga Beli')
                                    ->money('IDR', locale: 'id'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('diameter_terkecil')
                                    ->label('Diameter Terkecil (cm)')
                                    ->numeric(),

                                TextEntry::make('diameter_terbesar')
                                    ->label('Diameter Terbesar (cm)')
                                    ->numeric(),
                            ]),
                    ]),

                Section::make('Jenis Kayu')
                    ->schema([
                        TextEntry::make('jenisKayu.kode_kayu')
                            ->label('Kode Kayu'),
                        TextEntry::make('jenisKayu.nama_kayu')
                            ->label('Nama Kayu'),
                    ])
                    ->columns(2),

                Section::make('Informasi Waktu')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d F Y, H:i'),

                        TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime('d F Y, H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}