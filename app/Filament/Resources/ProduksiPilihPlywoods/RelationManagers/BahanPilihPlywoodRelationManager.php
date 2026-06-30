<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Filament\Resources\BahanPilihPlywoods\Schemas\BahanPilihPlywoodForm;
use App\Filament\Resources\BahanPilihPlywoods\Tables\BahanPilihPlywoodsTable;
use App\Filament\Resources\HasilPilihPlywoods\Tables\HasilPilihPlywoodsTable;
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

class BahanPilihPlywoodRelationManager extends RelationManager
{
    protected static string $relationship = 'BahanPilihPlywood';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanPilihPlywoodForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanPilihPlywoodsTable::configure($table);
    }
}
