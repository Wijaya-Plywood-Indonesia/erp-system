<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\HasilRepairs\Schemas\HasilRepairForm;
use App\Filament\Resources\HasilRepairs\Tables\HasilRepairsTable;


class HasilRepairRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilRepairs';
    // public function isReadOnly(): bool
    // {
    //     return false;
    // }
    public function form(Schema $schema): Schema
    {
        return HasilRepairForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        $idProduksiRepair = $this->getOwnerRecord()->id;
        $tanggalProduksi = $this->getOwnerRecord()->tanggal;

        return HasilRepairsTable::configure($table, $idProduksiRepair, $tanggalProduksi);
    }
}
