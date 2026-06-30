<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ModalRepairs\Schemas\ModalRepairForm;
use App\Filament\Resources\ModalRepairs\Tables\ModalRepairsTable;

class ModalRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'modalRepairs';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return ModalRepairForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ModalRepairsTable::configure($table);
    }
}
