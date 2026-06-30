<?php

namespace App\Filament\Resources\ProduksiRepairs\RelationManagers;

use App\Filament\Resources\RencanaPegawais\Schemas\RencanaPegawaiForm;
use App\Filament\Resources\RencanaPegawais\Tables\RencanaPegawaisTable;
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

class DetailRencanaPegawaiRelationManager extends RelationManager
{
    protected static string $relationship = 'rencanaPegawais';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return RencanaPegawaiForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return RencanaPegawaisTable::configure($table);
    }
}
