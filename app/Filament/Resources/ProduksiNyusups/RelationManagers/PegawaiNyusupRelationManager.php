<?php

namespace App\Filament\Resources\ProduksiNyusups\RelationManagers;

use App\Filament\Resources\PegawaiNyusups\Schemas\PegawaiNyusupForm;
use App\Filament\Resources\PegawaiNyusups\Tables\PegawaiNyusupsTable;
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

class PegawaiNyusupRelationManager extends RelationManager
{
    protected static string $relationship = 'PegawaiNyusup';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return PegawaiNyusupForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiNyusupsTable::configure($table);
    }
}
