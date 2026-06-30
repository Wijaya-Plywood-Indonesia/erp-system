<?php

namespace App\Filament\Resources\ModalRepairs\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\Ukuran;
use App\Models\JenisKayu;

class ModalRepairForm
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
                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::query()
                            ->get()
                            ->mapWithKeys(fn($jenis) => [
                                $jenis->id => "{$jenis->nama_kayu}",
                            ])
                    )
                    ->searchable()
                    ->required(),
                TextInput::make('jumlah')
                    ->label('Jumlah bahan')
                    ->required()
                    ->numeric(),
                TextInput::make('kw')
                    ->label('KW')
                    ->placeholder('Masukkan KW')
                    ->required(),
                TextInput::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->required()
                    ->numeric(),
            ]);
    }
}
