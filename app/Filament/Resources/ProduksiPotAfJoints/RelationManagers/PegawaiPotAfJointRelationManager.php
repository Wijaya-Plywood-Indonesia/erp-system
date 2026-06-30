<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\RelationManagers;

use App\Filament\Resources\PegawaiPotAfJoints\Schemas\PegawaiPotAfJointForm;
use App\Filament\Resources\PegawaiPotAfJoints\Tables\PegawaiPotAfJointsTable;
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

class PegawaiPotAfJointRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'PegawaiPotAfJoint';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PegawaiPotAfJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiPotAfJointsTable::configure($table);
    }
}
