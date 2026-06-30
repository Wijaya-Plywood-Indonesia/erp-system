<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\BahanPenolongHps\Schemas\BahanPenolongHpForm;
use App\Filament\Resources\BahanPenolongHps\Tables\BahanPenolongHpsTable;
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

class BahanPenolongHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Bahan Penolong';
    protected static string $relationship = 'bahanPenolongHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanPenolongHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanPenolongHpsTable::configure($table);
    }
}
