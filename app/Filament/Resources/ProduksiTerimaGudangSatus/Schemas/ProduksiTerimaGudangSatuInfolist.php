<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiTerimaGudangSatuInfolist
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
