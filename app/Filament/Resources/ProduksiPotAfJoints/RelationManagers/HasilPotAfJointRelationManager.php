<?php

namespace App\Filament\Resources\ProduksiPotAfJoints\RelationManagers;

use App\Filament\Resources\HasilPotAfJoints\Schemas\HasilPotAfJointForm;
use App\Filament\Resources\HasilPotAfJoints\Tables\HasilPotAfJointsTable;
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

class HasilPotAfJointRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'HasilPotAfJoint';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return HasilPotAfJointForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilPotAfJointsTable::configure($table);
    }
}
