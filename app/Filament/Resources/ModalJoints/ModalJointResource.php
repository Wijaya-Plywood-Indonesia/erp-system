<?php

namespace App\Filament\Resources\ModalJoints;

use App\Filament\Resources\ModalJoints\Pages\CreateModalJoint;
use App\Filament\Resources\ModalJoints\Pages\EditModalJoint;
use App\Filament\Resources\ModalJoints\Pages\ListModalJoints;
use App\Filament\Resources\ModalJoints\Schemas\ModalJointForm;
use App\Filament\Resources\ModalJoints\Tables\ModalJointsTable;
use App\Models\ModalJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModalJointResource extends Resource
{
    protected static ?string $model = ModalJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ModalJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModalJointsTable::configure($table);
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
            'index' => ListModalJoints::route('/'),
            'create' => CreateModalJoint::route('/create'),
            'edit' => EditModalJoint::route('/{record}/edit'),
        ];
    }
}
