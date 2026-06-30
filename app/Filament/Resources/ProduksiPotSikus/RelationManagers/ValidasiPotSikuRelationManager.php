<?php

namespace App\Filament\Resources\ProduksiPotSikus\RelationManagers;

use App\Filament\Resources\ValidasiPotSikus\Schemas\ValidasiPotSikuForm;
use App\Filament\Resources\ValidasiPotSikus\Tables\ValidasiPotSikusTable;
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

class ValidasiPotSikuRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'ValidasiPotSiku';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiPotSikuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiPotSikusTable::configure($table);
    }
}
