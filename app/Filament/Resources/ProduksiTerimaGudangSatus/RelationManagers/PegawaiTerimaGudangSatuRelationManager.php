<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Filament\Resources\PegawaiTerimaGudangSatus\Schemas\PegawaiTerimaGudangSatuForm;
use App\Filament\Resources\PegawaiTerimaGudangSatus\Tables\PegawaiTerimaGudangSatusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PegawaiTerimaGudangSatuRelationManager extends RelationManager
{
    protected static string $relationship = 'pegawaiTerimaGudangSatu';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiTerimaGudangSatuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiTerimaGudangSatusTable::configure($table);
    }
}
