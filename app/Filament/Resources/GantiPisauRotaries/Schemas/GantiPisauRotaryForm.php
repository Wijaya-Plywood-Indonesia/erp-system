<?php

namespace App\Filament\Resources\GantiPisauRotaries\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class GantiPisauRotaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Menggunakan Textarea langsung untuk jenis kendala
                Textarea::make('jenis_kendala')
                    ->label('Jenis Kendala')
                    ->required()
                    ->rows(3)
                    ->placeholder('Deskripsikan kendala...'),

                // Waktu Mulai (Otomatis)
                Hidden::make('jam_mulai_ganti_pisau')
                    ->default(now()->format('H:i')),
            ]);
    }
}   