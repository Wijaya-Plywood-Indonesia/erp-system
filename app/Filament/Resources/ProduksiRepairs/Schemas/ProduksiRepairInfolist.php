<?php

namespace App\Filament\Resources\ProduksiRepairs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProduksiRepairInfolist
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
