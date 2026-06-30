<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\RelationManagers;

use App\Filament\Resources\ModalPilihVeneers\Schemas\ModalPilihVeneerForm;
use App\Filament\Resources\ModalPilihVeneers\Tables\ModalPilihVeneersTable;
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

class ModalPilihVeneerRelationManager extends RelationManager
{
    protected static string $relationship = 'ModalPilihVeneer';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ModalPilihVeneerForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ModalPilihVeneersTable::configure($table);
    }
}
