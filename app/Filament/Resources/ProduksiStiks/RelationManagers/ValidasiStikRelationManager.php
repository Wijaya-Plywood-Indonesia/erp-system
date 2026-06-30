<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use App\Filament\Resources\ValidasiStiks\Schemas\ValidasiStikForm;
use App\Filament\Resources\ValidasiStiks\Tables\ValidasiStiksTable;
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

class ValidasiStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'validasiStik';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiStikForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiStiksTable::configure($table);
    }
}
