<?php

namespace App\Filament\Resources\DetailBongkarKedis\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use App\Models\JenisKayu;
use App\Models\Ukuran;

class DetailBongkarKediForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->required()
                    ->numeric(),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(
                        JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')
                    )
                    ->searchable()
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->sortBy(fn($u) => $u->dimensi)
                            ->mapWithKeys(fn($u) => [$u->id => $u->dimensi])
                    )
                    ->searchable()
                    ->required(),

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->required(),
            ]);
    }
}
