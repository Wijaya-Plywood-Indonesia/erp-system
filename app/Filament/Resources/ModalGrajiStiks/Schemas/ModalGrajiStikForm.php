<?php

namespace App\Filament\Resources\ModalGrajiStiks\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\Ukuran;

class ModalGrajiStikForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->pluck('dimensi', 'id') // ← memanggil accessor getDimensiAttribute()
                    )
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_ukuran' => $state]);
                    })
                    ->default(fn() => session('last_ukuran'))
                    ->required(),
                TextInput::make('jumlah_bahan')
                    ->label('Jumlah bahan')
                    ->required()
                    ->numeric(),
                TextInput::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->required()
                    ->numeric(),
            ]);
    }
}
