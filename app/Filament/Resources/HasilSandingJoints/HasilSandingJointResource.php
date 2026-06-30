<?php

namespace App\Filament\Resources\HasilSandingJoints;

use App\Filament\Resources\HasilSandingJoints\Pages\CreateHasilSandingJoint;
use App\Filament\Resources\HasilSandingJoints\Pages\EditHasilSandingJoint;
use App\Filament\Resources\HasilSandingJoints\Pages\ListHasilSandingJoints;
use App\Filament\Resources\HasilSandingJoints\Schemas\HasilSandingJointForm;
use App\Filament\Resources\HasilSandingJoints\Tables\HasilSandingJointsTable;
use App\Models\HasilSandingJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilSandingJointResource extends Resource
{
    protected static ?string $model = HasilSandingJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'n';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilSandingJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilSandingJointsTable::configure($table);
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
            'index' => ListHasilSandingJoints::route('/'),
            'create' => CreateHasilSandingJoint::route('/create'),
            'edit' => EditHasilSandingJoint::route('/{record}/edit'),
        ];
    }
}
