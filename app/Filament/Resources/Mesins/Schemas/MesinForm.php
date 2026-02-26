<?php

namespace App\Filament\Resources\Mesins\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MesinForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kategori_mesin_id')
                    ->label('Kategori Mesin')
                    ->relationship('kategoriMesin', 'nama_kategori_mesin') // relasi dan kolom yang ditampilkan
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('nama_mesin')
                    ->required(),
                TextInput::make('ongkos_mesin')
                    ->required()
                    ->numeric(),
                TextInput::make('penyusutan')
                    ->required()
                    ->numeric(),
                TextInput::make('no_akun')
                    ->required(),
                Textarea::make('detail_mesin')
                    ->columnSpanFull(),
            ]);
    }
}
