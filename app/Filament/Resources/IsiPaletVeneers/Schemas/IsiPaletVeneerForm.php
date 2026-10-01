<?php

namespace App\Filament\Resources\IsiPaletVeneers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class IsiPaletVeneerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->searchable()
                    ->preload(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->relationship('ukuran', 'panjang')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->nama_ukuran)
                    ->searchable(['panjang', 'lebar', 'tebal'])
                    ->preload()
                    ->required(),

                TextInput::make('jumlah_lembar')
                    ->label('Jumlah Lembar')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                Textarea::make('keterangan')
                    ->label('Keterangan')
                    ->columnSpanFull(),
            ]);
    }
}
