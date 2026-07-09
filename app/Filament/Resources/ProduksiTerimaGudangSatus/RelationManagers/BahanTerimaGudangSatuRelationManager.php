<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Filament\Resources\BahanTerimaGudangSatus\Schemas\BahanTerimaGudangSatuForm;
use App\Filament\Resources\BahanTerimaGudangSatus\Tables\BahanTerimaGudangSatusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BahanTerimaGudangSatuRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanTerimaGudangSatu';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanTerimaGudangSatuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanTerimaGudangSatusTable::configure($table);
    }
}
