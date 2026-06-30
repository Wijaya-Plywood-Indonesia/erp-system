<?php

namespace App\Filament\Resources\NotaBarangKeluars\RelationManagers;

use App\Filament\Resources\DetailNotaBarangKeluars\DetailNotaBarangKeluarResource;
use App\Filament\Resources\DetailNotaBarangKeluars\Schemas\DetailNotaBarangKeluarForm;
use App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailRelationManager extends RelationManager
{
    protected static string $relationship = 'detail';
    public function form(Schema $schema): Schema
    {
        return DetailNotaBarangKeluarForm::configure($schema);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return DetailNotaBarangKeluarsTable::configure($table);
    }
}
