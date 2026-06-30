<?php

namespace App\Filament\Resources\ProduksiPotSikus\RelationManagers;

use App\Filament\Resources\PegawaiPotSikus\Schemas\PegawaiPotSikuForm;
use App\Filament\Resources\PegawaiPotSikus\Tables\PegawaiPotSikusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PegawaiPotSikuRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'PegawaiPotSiku';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiPotSikuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiPotSikusTable::configure($table);
    }
}
