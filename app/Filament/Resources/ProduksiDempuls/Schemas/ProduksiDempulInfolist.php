<?php

namespace App\Filament\Resources\ProduksiDempuls\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

class ProduksiDempulInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->label('Tanggal')
                    ->date(),
                TextEntry::make('kendala')
                    ->label('Kendala')
                    ->wrap(),
            ]);
    }
}
