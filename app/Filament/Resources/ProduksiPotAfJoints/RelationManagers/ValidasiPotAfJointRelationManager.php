<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\RelationManagers;

use App\Filament\Resources\ValidasiPotAfJoints\Schemas\ValidasiPotAfJointForm;
use App\Filament\Resources\ValidasiPotAfJoints\Tables\ValidasiPotAfJointsTable;
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

class ValidasiPotAfJointRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'ValidasiPotAfJoint';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiPotAfJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiPotAfJointsTable::configure($table);
    }
}
