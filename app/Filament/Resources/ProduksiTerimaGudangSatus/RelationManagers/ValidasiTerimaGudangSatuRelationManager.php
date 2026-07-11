<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Filament\Resources\ValidasiTerimaGudangSatus\Schemas\ValidasiTerimaGudangSatuForm;
use App\Filament\Resources\ValidasiTerimaGudangSatus\Tables\ValidasiTerimaGudangSatusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ValidasiTerimaGudangSatuRelationManager extends RelationManager
{
    protected static string $relationship = 'validasiTerimaGudangSatu';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiTerimaGudangSatuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiTerimaGudangSatusTable::configure($table);
    }
}
