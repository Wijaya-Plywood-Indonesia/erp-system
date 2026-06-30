<?php

namespace App\Filament\Resources\ProduksiDempuls\RelationManagers;


use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

use App\Filament\Resources\ValidasiDempuls\Schemas\ValidasiDempulForm;
use App\Filament\Resources\ValidasiDempuls\Tables\ValidasiDempulsTable;

class ValidasiDempulRelationManager extends RelationManager
{
    protected static string $relationship = 'validasiDempuls';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiDempulForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiDempulsTable::configure($table);
    }
}
