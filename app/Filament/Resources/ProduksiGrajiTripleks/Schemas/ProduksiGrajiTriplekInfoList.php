<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiGrajiTriplekInfoList
{
    public static function configure(Schema $schema): schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi')
                    ->date(),
                TextEntry::make('status'),
                TextEntry::make('kendala'),
                TextEntry::make('shift')
            ]);
    }
}
