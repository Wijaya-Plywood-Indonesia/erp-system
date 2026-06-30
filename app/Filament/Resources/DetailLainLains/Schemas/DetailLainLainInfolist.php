<?php

namespace App\Filament\Resources\DetailLainLains\Schemas;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DetailLainLainInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->label('Tanggal')
                    ->date(),
            ]);
    }
}
