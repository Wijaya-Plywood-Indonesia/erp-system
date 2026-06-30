<?php

namespace App\Filament\Resources\DetailLainLains\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\DatePicker;

class DetailLainLainForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('tanggal')
                    ->label('Tanggal')
                    ->native(false)                    // modern, responsive
                    ->format('Y-m-d')                     // format penyimpanan
                    ->displayFormat('d/m/Y')             // tampil di UI
                    ->live()
                    ->closeOnDateSelection()
                    ->required()
                    ->maxDate(now()->addDays(30))
                    ->default(now()->addDay())
            ]);
    }
}
