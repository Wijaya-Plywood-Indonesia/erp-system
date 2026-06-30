<?php

namespace App\Filament\Resources\DetailHasils\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class DetailHasilForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('no_palet')
                    ->label('No. Palet')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('HSL-001'),

                TextInput::make('kw')
                    ->label('KW')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('KW2'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('95'),

                Select::make('id_kayu_masuk')
                    ->label('Kayu Masuk')
                    ->relationship('kayuMasuk', 'seri')
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Kayu Masuk')
                    ->nullable(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->searchable()
                    ->preload()
                    ->placeholder('Pilih Jenis Kayu')
                    ->nullable(),

                Select::make('id_produksi_dryer')
                    ->label('Produksi Dryer')
                    ->relationship('produksiDryer', 'tanggal_produksi')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) =>
                        $record->tanggal_produksi->format('d M Y') . ' | ' . $record->shift
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->placeholder('Pilih Produksi Dryer'),
            ]);
    }
}
