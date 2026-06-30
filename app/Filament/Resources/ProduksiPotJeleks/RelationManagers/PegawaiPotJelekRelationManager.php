<?php

namespace App\Filament\Resources\ProduksiPotJeleks\RelationManagers;

use App\Filament\Resources\PegawaiPotJeleks\Schemas\PegawaiPotJelekForm;
use App\Filament\Resources\PegawaiPotJeleks\Tables\PegawaiPotJeleksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PegawaiPotJelekRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'PegawaiPotJelek';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiPotJelekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiPotJeleksTable::configure($table);
    }
}
