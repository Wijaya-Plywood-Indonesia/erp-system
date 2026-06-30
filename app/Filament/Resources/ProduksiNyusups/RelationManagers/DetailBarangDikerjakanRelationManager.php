<?php

namespace App\Filament\Resources\ProduksiNyusups\RelationManagers;

use App\Filament\Resources\DetailBarangDikerjakans\Schemas\DetailBarangDikerjakanForm;
use App\Filament\Resources\DetailBarangDikerjakans\Tables\DetailBarangDikerjakansTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailBarangDikerjakanRelationManager extends RelationManager
{
    protected static string $relationship = 'DetailBarangDikerjakan';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailBarangDikerjakansTable::configure($table);
    }
}
