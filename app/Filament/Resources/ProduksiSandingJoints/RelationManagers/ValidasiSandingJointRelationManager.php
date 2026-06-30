<?php

namespace App\Filament\Resources\ProduksiSandingJoints\RelationManagers;

use App\Filament\Resources\ValidasiSandingJoints\Schemas\ValidasiSandingJointForm;
use App\Filament\Resources\ValidasiSandingJoints\Tables\ValidasiSandingJointsTable;
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

class ValidasiSandingJointRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiSandingJoint';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return ValidasiSandingJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiSandingJointsTable::configure($table);
    }
}
