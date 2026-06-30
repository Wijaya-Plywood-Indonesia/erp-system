<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\RelationManagers;

use App\Filament\Resources\MasukGrajiTripleks\Schemas\MasukGrajiTriplekForm;
use App\Filament\Resources\MasukGrajiTripleks\Tables\MasukGrajiTripleksTable;
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

class MasukGrajiTriplekRelationManager extends RelationManager
{
    protected static ?string $title = 'Modal';
    protected static string $relationship = 'MasukGrajiTriplek';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return MasukGrajiTriplekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return MasukGrajiTripleksTable::configure($table);
    }
}
