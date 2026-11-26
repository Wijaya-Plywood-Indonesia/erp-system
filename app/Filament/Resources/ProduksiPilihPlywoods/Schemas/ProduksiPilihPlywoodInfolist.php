<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiPilihPlywoodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi')
                    ->date(),
                TextEntry::make('kendala'),
            ]);
    }
}
