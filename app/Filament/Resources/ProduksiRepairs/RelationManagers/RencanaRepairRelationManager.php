<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\RencanaRepairs\Tables\RencanaRepairsTable;
use App\Filament\Resources\RencanaRepairs\Schemas\RencanaRepairForm;

class RencanaRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'rencanaRepair';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return RencanaRepairForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RencanaRepairsTable::configure($table);
    }
}
