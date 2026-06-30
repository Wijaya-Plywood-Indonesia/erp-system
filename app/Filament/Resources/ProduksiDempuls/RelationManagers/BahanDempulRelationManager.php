<?php

namespace App\Filament\Resources\ProduksiDempuls\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\BahanDempuls\Schemas\BahanDempulForm;
use App\Filament\Resources\BahanDempuls\Tables\BahanDempulsTable;

class BahanDempulRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanDempuls';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanDempulForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanDempulsTable::configure($table);
    }
}
