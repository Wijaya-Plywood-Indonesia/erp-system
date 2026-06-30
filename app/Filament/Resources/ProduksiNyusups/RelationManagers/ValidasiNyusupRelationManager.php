<?php

namespace App\Filament\Resources\ProduksiNyusups\RelationManagers;

use App\Filament\Resources\ValidasiNyusups\Schemas\ValidasiNyusupForm;
use App\Filament\Resources\ValidasiNyusups\Tables\ValidasiNyusupsTable;
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

class ValidasiNyusupRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiNyusup';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return ValidasiNyusupForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiNyusupsTable::configure($table);
    }
}
