<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\RelationManagers;

use App\Filament\Resources\HasilPilihVeneers\Schemas\HasilPilihVeneerForm;
use App\Filament\Resources\HasilPilihVeneers\Tables\HasilPilihVeneersTable;
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

class HasilPilihVeneerRelationManager extends RelationManager
{
    protected static string $relationship = 'HasilPilihVeneer';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilPilihVeneerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilPilihVeneersTable::configure($table);
    }
}
