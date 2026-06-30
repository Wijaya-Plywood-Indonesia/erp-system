<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ValidasiRepairs\Schemas\ValidasiRepairForm;
use App\Filament\Resources\ValidasiRepairs\Tables\ValidasiRepairsTable;

class ValidasiRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'validasiRepairs';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return ValidasiRepairForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiRepairsTable::configure($table);
    }
}
