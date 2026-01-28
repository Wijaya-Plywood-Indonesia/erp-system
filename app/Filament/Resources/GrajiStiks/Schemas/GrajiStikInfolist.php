<?php

namespace App\Filament\Resources\GrajiStiks\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

class GrajiStikInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->label('Tanggal Produksi')
                    ->date(),

                TextEntry::make('kendala')
                    ->label('Kendala'),
            ]);
    }
}
