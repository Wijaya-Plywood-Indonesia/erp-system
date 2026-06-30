<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\RelationManagers;

use App\Filament\Resources\ValidasiPilihVeneers\Schemas\ValidasiPilihVeneerForm;
use App\Filament\Resources\ValidasiPilihVeneers\Tables\ValidasiPilihVeneersTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ValidasiPilihVeneerRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiPilihVeneer';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiPilihVeneerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiPilihVeneersTable::configure($table);
    }
}
