<?php

namespace App\Filament\Resources\ProduksiGuellotines\RelationManagers;

use App\Filament\Resources\PegawaiGuellotines\Schemas\PegawaiGuellotineForm;
use App\Filament\Resources\PegawaiGuellotines\Tables\PegawaiGuellotinesTable;
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

class PegawaiGuellotineRelationManager extends RelationManager
{
    protected static string $relationship = 'PegawaiGuellotine';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiGuellotineForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiGuellotinesTable::configure($table);
    }
}
