<?php

namespace App\Filament\Resources\ValidasiPotAfJoints;

use App\Filament\Resources\ValidasiPotAfJoints\Pages\CreateValidasiPotAfJoint;
use App\Filament\Resources\ValidasiPotAfJoints\Pages\EditValidasiPotAfJoint;
use App\Filament\Resources\ValidasiPotAfJoints\Pages\ListValidasiPotAfJoints;
use App\Filament\Resources\ValidasiPotAfJoints\Schemas\ValidasiPotAfJointForm;
use App\Filament\Resources\ValidasiPotAfJoints\Tables\ValidasiPotAfJointsTable;
use App\Models\ValidasiPotAfJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiPotAfJointResource extends Resource
{
    protected static ?string $model = ValidasiPotAfJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiPotAfJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPotAfJointsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListValidasiPotAfJoints::route('/'),
            'create' => CreateValidasiPotAfJoint::route('/create'),
            'edit' => EditValidasiPotAfJoint::route('/{record}/edit'),
        ];
    }
}
