<?php

namespace App\Filament\Resources\ProduksiRotaries\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiRotaryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // 1. BAGIAN INFORMASI UTAMA
                Section::make('Informasi Produksi')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Grid::make(2) // Bagi menjadi 2 kolom
                            ->schema([
                                TextEntry::make('mesin.nama_mesin')
                                    ->label('Nama Mesin')
                                    ->weight('bold')
                                    ->icon('heroicon-o-cpu-chip'),

                                TextEntry::make('tgl_produksi')
                                    ->label('Tanggal Produksi')
                                    ->date('d F Y')
                                    ->icon('heroicon-o-calendar'),
                            ]),
                    ]),
                    
                // 3. BAGIAN METADATA (Riwayat Data)
                Section::make('Riwayat Data')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime('d M Y • H:i'),

                                TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime('d M Y • H:i'),
                            ]),
                    ])
                    ->compact(),  // Tampilan lebih padat
            ]);
    }
}