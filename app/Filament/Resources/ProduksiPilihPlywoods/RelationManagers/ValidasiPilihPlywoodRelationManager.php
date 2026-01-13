<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Filament\Resources\ValidasiPilihPlywoods\Schemas\ValidasiPilihPlywoodForm;
use App\Filament\Resources\ValidasiPilihPlywoods\Tables\ValidasiPilihPlywoodsTable;
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

class ValidasiPilihPlywoodRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiPilihPlywood';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiPilihPlywoodForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiPilihPlywoodsTable::configure($table);
    }
}
