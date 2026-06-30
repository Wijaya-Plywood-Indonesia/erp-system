<?php

namespace App\Filament\Resources\DetailLainLains\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

use App\Filament\Resources\LainLains\Schemas\LainLainForm;
use App\Filament\Resources\LainLains\Tables\LainLainsTable;

class LainLainRelationManager extends RelationManager
{
    protected static string $relationship = 'lainLains';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return LainLainForm::configure($schema);
    }

    public function table(Table $table): Table
    {

        return LainLainsTable::configure($table);
    }
}
