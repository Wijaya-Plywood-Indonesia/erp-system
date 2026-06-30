<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiGrajiBalkenInfoList
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal_produksi')
                    ->label('Tanggal Produksi')
                    ->date(),

                TextEntry::make('kendala')
                    ->label('Kendala'),
            ]);
    }
}
