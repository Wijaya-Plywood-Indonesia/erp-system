<?php

namespace App\Filament\Resources\ProduksiDempuls\RelationManagers;

use App\Filament\Resources\DetailDempuls\Schemas\DetailDempulForm;
use App\Filament\Resources\DetailDempuls\Tables\DetailDempulsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailDempulRelationManager extends RelationManager
{
    protected static string $relationship = 'detailDempuls';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DetailDempulForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailDempulsTable::configure($table);
    }
}
