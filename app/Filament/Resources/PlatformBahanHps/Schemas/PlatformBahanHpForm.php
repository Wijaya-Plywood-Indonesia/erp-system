<?php

namespace App\Filament\Resources\PlatformBahanHps\Schemas;

use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class PlatformBahanHpForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Relasi ke Jenis Kayu
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')
                    )
                    ->searchable()
                    ->afterStateUpdated(function ($state) {
                        session(['last_jenis_kayu' => $state]);
                    })
                    ->default(fn() => session('last_jenis_kayu'))
                    ->required(),

                // Relasi ke Kayu Masuk (Optional)
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
                    ->required(), // Sesuai dengan migrasi

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Cth: 1, 2, 3,dll.'),

                TextInput::make('isi')
                    ->label('Isi')
                    ->required()
                    ->numeric()
                    ->placeholder('Cth: 1.5 atau 100'),
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),
            ]);
    }
}
