<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Filament\Resources\BahanPenolongRotaries\Schemas\BahanPenolongRotaryForm;
use App\Filament\Resources\BahanPenolongRotaries\Tables\BahanPenolongRotariesTable;
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

class BahanPenolongRotaryRelationManager extends RelationManager
{
    protected static string $relationship = 'BahanPenolongRotary';
    protected static ?string $title = 'Bahan Digunakan';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanPenolongRotaryForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanPenolongRotariesTable::configure($table);
    }
}
