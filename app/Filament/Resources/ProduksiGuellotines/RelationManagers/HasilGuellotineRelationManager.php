<?php

namespace App\Filament\Resources\ProduksiGuellotines\RelationManagers;

use App\Filament\Resources\HasilGuellotines\Schemas\HasilGuellotineForm;
use App\Filament\Resources\HasilGuellotines\Tables\HasilGuellotinesTable;
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

class HasilGuellotineRelationManager extends RelationManager
{
    protected static string $relationship = 'HasilGuellotine';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilGuellotineForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilGuellotinesTable::configure($table);
    }
}
