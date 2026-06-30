<?php

namespace App\Filament\Resources\ProduksiSandings\Schemas;

use App\Models\Mesin;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProduksiSandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->native(false)
                    ->locale('id')                          // Bahasa Indonesia di kalender
                    ->format('Y-m-d')                      // format simpan DB
                    ->displayFormat('l, j F Y')            // Rabu, 1 Januari 2025
                    ->live()
                    ->closeOnDateSelection()
                    ->required()
                    ->maxDate(now()->addDays(30))
                    ->default(now()->addDay()),

                Select::make('id_mesin')
                    ->label('Mesin')
                    // ->multiple()
                    ->options(function () {
                        return Mesin::query()
                            ->where(function ($q) {
                                $q->where('kategori_mesin_id', 1)
                                    ->orWhereHas(
                                        'kategoriMesin',
                                        fn($q2) =>
                                        $q2->where('nama_kategori_mesin', 'SANDING')
                                    );
                            })
                            ->pluck('nama_mesin', 'id');
                    })
                    ->required(),

                Select::make('shift')
                    ->label('Shift')
                    ->options([
                        'PAGI' => 'Pagi',
                        'MALAM' => 'Malam',
                    ])
                    ->required()
                    ->native(false),
            ]);
    }
}
