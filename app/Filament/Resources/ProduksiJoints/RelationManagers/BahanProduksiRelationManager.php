<?php

namespace App\Filament\Resources\ProduksiJoints\RelationManagers;

use App\Filament\Resources\BahanProduksis\Schemas\BahanProduksiForm;
use App\Filament\Resources\BahanProduksis\Tables\BahanProduksisTable;
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

class BahanProduksiRelationManager extends RelationManager
{
    protected static string $relationship = 'bahanProduksi';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanProduksiForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanProduksisTable::configure($table);
    }
}
