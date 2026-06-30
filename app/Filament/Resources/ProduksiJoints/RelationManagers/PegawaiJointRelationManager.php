<?php

namespace App\Filament\Resources\ProduksiJoints\RelationManagers;

use App\Filament\Resources\PegawaiJoints\Schemas\PegawaiJointForm;
use App\Filament\Resources\PegawaiJoints\Tables\PegawaiJointsTable;
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

class PegawaiJointRelationManager extends RelationManager
{
    protected static string $relationship = 'PegawaiJoint';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiJointsTable::configure($table);
    }
}
