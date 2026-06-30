<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\RelationManagers;

use App\Filament\Resources\PegawaiGrajiTripleks\Schemas\PegawaiGrajiTriplekForm;
use App\Filament\Resources\PegawaiGrajiTripleks\Tables\PegawaiGrajiTripleksTable;
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

class PegawaiGrajiTriplekRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'PegawaiGrajiTriplek';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiGrajiTriplekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiGrajiTripleksTable::configure($table);
    }
}
