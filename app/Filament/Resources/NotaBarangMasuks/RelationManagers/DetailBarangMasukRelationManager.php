<?php

namespace App\Filament\Resources\NotaBarangMasuks\RelationManagers;

use App\Filament\Resources\DetailNotaBarangMasuks\Schemas\DetailNotaBarangMasukForm;
use App\Filament\Resources\DetailNotaBarangMasuks\Tables\DetailNotaBarangMasuksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailBarangMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'detail';

    public function form(Schema $schema): Schema
    {
        return DetailNotaBarangMasukForm::configure($schema);
    }
    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return DetailNotaBarangMasuksTable::configure($table);
    }
}
