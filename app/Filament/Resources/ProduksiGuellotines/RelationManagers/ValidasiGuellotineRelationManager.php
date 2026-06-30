<?php

namespace App\Filament\Resources\ProduksiGuellotines\RelationManagers;

use App\Filament\Resources\ValidasiGuellotines\Schemas\ValidasiGuellotineForm;
use App\Filament\Resources\ValidasiGuellotines\Tables\ValidasiGuellotinesTable;
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

class ValidasiGuellotineRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiGuellotine';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiGuellotineForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiGuellotinesTable::configure($table);
    }
}
