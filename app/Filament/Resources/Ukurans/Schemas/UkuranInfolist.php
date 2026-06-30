<?php

namespace App\Filament\Resources\Ukurans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UkuranInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('panjang'),
                TextEntry::make('lebar'),
                TextEntry::make('tebal'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
