<?php

namespace App\Filament\Resources\HasilJoints;

use App\Filament\Resources\HasilJoints\Pages\CreateHasilJoint;
use App\Filament\Resources\HasilJoints\Pages\EditHasilJoint;
use App\Filament\Resources\HasilJoints\Pages\ListHasilJoints;
use App\Filament\Resources\HasilJoints\Schemas\HasilJointForm;
use App\Filament\Resources\HasilJoints\Tables\HasilJointsTable;
use App\Models\HasilJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilJointResource extends Resource
{
    protected static ?string $model = HasilJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilJointsTable::configure($table);
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
            'index' => ListHasilJoints::route('/'),
            'create' => CreateHasilJoint::route('/create'),
            'edit' => EditHasilJoint::route('/{record}/edit'),
        ];
    }
}
