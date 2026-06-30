<?php

namespace App\Filament\Resources\TurunKayus\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TurunKayusInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('tanggal')
                    ->numeric(),
                TextEntry::make('kendala'),
            ]);
    }
}
