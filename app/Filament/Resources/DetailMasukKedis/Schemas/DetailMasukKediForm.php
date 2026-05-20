<?php

namespace App\Filament\Resources\DetailMasukKedis\Schemas;

use Filament\Schemas\Schema;

use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Models\Mesin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;

class DetailMasukKediForm
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
                            ->sortBy(fn($u) => $u->dimensi)
                            ->mapWithKeys(fn($u) => [$u->id => $u->dimensi])
                    )
                    ->searchable()

                    ->required(),

                // Select::make('id_jenis_kayu')
                //     ->label('Jenis Kayu')
                //     ->options(
                //         JenisKayu::query()
                //             ->get()
                //             ->mapWithKeys(function ($JenisKayu) {
                //                 return [
                //                     $JenisKayu->id => "{$JenisKayu->kode_kayu} - {$JenisKayu->nama_kayu}",
                //                 ];
                //             })
                //     )
                //     ->searchable()
                //     ->required(),


                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required(),


                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->required(),
                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),
            ]);
    }
}
