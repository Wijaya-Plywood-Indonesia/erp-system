<?php

namespace App\Filament\Resources\GradeRules\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class GradeRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id_grade')
                    ->numeric(),
                TextEntry::make('id_criteria')
                    ->numeric(),
                TextEntry::make('kondisi'),
                TextEntry::make('poin_lulus')
                    ->numeric(),
                TextEntry::make('poin_parsial')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
