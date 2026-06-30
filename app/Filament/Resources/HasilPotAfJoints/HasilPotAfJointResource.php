<?php

namespace App\Filament\Resources\HasilPotAfJoints;

use App\Filament\Resources\HasilPotAfJoints\Pages\CreateHasilPotAfJoint;
use App\Filament\Resources\HasilPotAfJoints\Pages\EditHasilPotAfJoint;
use App\Filament\Resources\HasilPotAfJoints\Pages\ListHasilPotAfJoints;
use App\Filament\Resources\HasilPotAfJoints\Schemas\HasilPotAfJointForm;
use App\Filament\Resources\HasilPotAfJoints\Tables\HasilPotAfJointsTable;
use App\Models\HasilPotAfJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilPotAfJointResource extends Resource
{
    protected static ?string $model = HasilPotAfJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilPotAfJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilPotAfJointsTable::configure($table);
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
            'index' => ListHasilPotAfJoints::route('/'),
            'create' => CreateHasilPotAfJoint::route('/create'),
            'edit' => EditHasilPotAfJoint::route('/{record}/edit'),
        ];
    }
}
