<?php

namespace App\Filament\Resources\ProduksiPressDryers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiPressDryerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi'),
                TextEntry::make('shift'),
                TextEntry::make('kendala'),
            ]);
    }
}
