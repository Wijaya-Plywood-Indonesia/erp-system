<?php

namespace App\Filament\Resources\ProduksiDempuls\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\RencanaPegawaiDempuls\Tables\RencanaPegawaiDempulsTable;
use App\Filament\Resources\RencanaPegawaiDempuls\Schemas\RencanaPegawaiDempulForm;

class RencanaPegawaiDempulRelationManager extends RelationManager
{
    protected static string $relationship = 'rencanaPegawaiDempuls';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return RencanaPegawaiDempulForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RencanaPegawaiDempulsTable::configure($table);
    }
}
