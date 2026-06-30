<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\RelationManagers;

use App\Filament\Resources\HasilGrajiTripleks\Schemas\HasilGrajiTriplekForm;
use App\Filament\Resources\HasilGrajiTripleks\Tables\HasilGrajiTripleksTable;
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

class HasilGrajiTriplekRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'HasilGrajiTriplek';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilGrajiTriplekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilGrajiTripleksTable::configure($table);
    }
}
