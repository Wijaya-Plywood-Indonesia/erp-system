<?php

namespace App\Filament\Resources\ProduksiStiks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiStikInfoList
{
    public static function configure(Schema $schema): schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi'),
                TextEntry::make('kendala'),
            ]);
    }
}
