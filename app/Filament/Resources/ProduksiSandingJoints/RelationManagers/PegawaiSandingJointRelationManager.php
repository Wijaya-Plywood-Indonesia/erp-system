<?php

namespace App\Filament\Resources\ProduksiSandingJoints\RelationManagers;

use App\Filament\Resources\PegawaiSandingJoints\Schemas\PegawaiSandingJointForm;
use App\Filament\Resources\PegawaiSandingJoints\Tables\PegawaiSandingJointsTable;
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

class PegawaiSandingJointRelationManager extends RelationManager
{
    protected static string $relationship = 'PegawaiSandingJoint';

    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return PegawaiSandingJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiSandingJointsTable::configure($table);
    }
}
