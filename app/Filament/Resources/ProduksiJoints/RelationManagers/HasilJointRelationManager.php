<?php

namespace App\Filament\Resources\ProduksiJoints\RelationManagers;

use App\Filament\Resources\HasilJoints\Schemas\HasilJointForm;
use App\Filament\Resources\HasilJoints\Tables\HasilJointsTable;
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

class HasilJointRelationManager extends RelationManager
{
    protected static string $relationship = 'HasilJoint';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilJointsTable::configure($table);
    }
}
