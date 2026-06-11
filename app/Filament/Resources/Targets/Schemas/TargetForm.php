<?php

namespace App\Filament\Resources\Targets\Schemas;

use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TargetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_mesin')
                    ->label('Mesin')
                    ->relationship('mesin', 'nama_mesin')
                    ->required()
                    ->reactive()
                    ->dehydrated(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->pluck('dimensi', 'id') // ← memanggil accessor getDimensiAttribute()
                    )
                    ->searchable()
                    ->required(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->relationship('jenisKayu', 'nama_kayu')
                    ->required()
                    ->dehydrated() // pastikan nilainya ikut submit
                    ->reactive(),

                TextInput::make('ukuran')
                    ->label('Kode Ukuran')
                    ->disabled()
                    ->dehydrated()
                    ->reactive(),


                TextInput::make('target')
                    ->label('Target')
                    ->numeric()
                    ->step(0.0001)
                    ->required(),

                TextInput::make('orang')
                    ->label('Jumlah Orang')
                    ->numeric()
                    ->required(),

                TextInput::make('jam')
                    ->label('Jam Kerja')
                    ->numeric()
                    ->required(),

                TextInput::make('gaji')
                    ->label('Gaji')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
            ]);
    }
}
