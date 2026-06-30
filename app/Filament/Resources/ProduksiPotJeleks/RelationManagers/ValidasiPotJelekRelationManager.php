<?php

namespace App\Filament\Resources\ProduksiPotjeleks\RelationManagers;

use App\Filament\Resources\ValidasiPotJeleks\Schemas\ValidasiPotJelekForm;
use App\Filament\Resources\ValidasiPotJeleks\Tables\ValidasiPotJeleksTable;
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

class ValidasiPotJelekRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'ValidasiPotJelek';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiPotJelekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiPotJeleksTable::configure($table);
    }
}
