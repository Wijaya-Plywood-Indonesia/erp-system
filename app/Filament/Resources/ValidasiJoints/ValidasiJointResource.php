<?php

namespace App\Filament\Resources\ValidasiJoints;

use App\Filament\Resources\ValidasiJoints\Pages\CreateValidasiJoint;
use App\Filament\Resources\ValidasiJoints\Pages\EditValidasiJoint;
use App\Filament\Resources\ValidasiJoints\Pages\ListValidasiJoints;
use App\Filament\Resources\ValidasiJoints\Schemas\ValidasiJointForm;
use App\Filament\Resources\ValidasiJoints\Tables\ValidasiJointsTable;
use App\Models\ValidasiJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiJointResource extends Resource
{
    protected static ?string $model = ValidasiJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiJointsTable::configure($table);
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
            'index' => ListValidasiJoints::route('/'),
            'create' => CreateValidasiJoint::route('/create'),
            'edit' => EditValidasiJoint::route('/{record}/edit'),
        ];
    }
}
