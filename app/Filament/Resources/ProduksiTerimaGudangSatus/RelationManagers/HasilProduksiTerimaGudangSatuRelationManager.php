<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Filament\Resources\HasilTerimaGudangSatus\Schemas\HasilTerimaGudangSatuForm;
use App\Filament\Resources\HasilTerimaGudangSatus\Tables\HasilTerimaGudangSatusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class HasilProduksiTerimaGudangSatuRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil Terima Gudang Satu';

    protected static string $relationship = 'hasil';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilTerimaGudangSatuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilTerimaGudangSatusTable::configure($table);
    }
}
