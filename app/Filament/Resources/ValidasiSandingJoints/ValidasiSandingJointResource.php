<?php

namespace App\Filament\Resources\ValidasiSandingJoints;

use App\Filament\Resources\ValidasiSandingJoints\Pages\CreateValidasiSandingJoint;
use App\Filament\Resources\ValidasiSandingJoints\Pages\EditValidasiSandingJoint;
use App\Filament\Resources\ValidasiSandingJoints\Pages\ListValidasiSandingJoints;
use App\Filament\Resources\ValidasiSandingJoints\Schemas\ValidasiSandingJointForm;
use App\Filament\Resources\ValidasiSandingJoints\Tables\ValidasiSandingJointsTable;
use App\Models\ValidasiSandingJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiSandingJointResource extends Resource
{
    protected static ?string $model = ValidasiSandingJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiSandingJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiSandingJointsTable::configure($table);
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
            'index' => ListValidasiSandingJoints::route('/'),
            'create' => CreateValidasiSandingJoint::route('/create'),
            'edit' => EditValidasiSandingJoint::route('/{record}/edit'),
        ];
    }
}
