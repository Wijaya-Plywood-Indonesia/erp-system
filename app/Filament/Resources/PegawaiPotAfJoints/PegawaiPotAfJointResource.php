<?php

namespace App\Filament\Resources\PegawaiPotAfJoints;

use App\Filament\Resources\PegawaiPotAfJoints\Pages\CreatePegawaiPotAfJoint;
use App\Filament\Resources\PegawaiPotAfJoints\Pages\EditPegawaiPotAfJoint;
use App\Filament\Resources\PegawaiPotAfJoints\Pages\ListPegawaiPotAfJoints;
use App\Filament\Resources\PegawaiPotAfJoints\Schemas\PegawaiPotAfJointForm;
use App\Filament\Resources\PegawaiPotAfJoints\Tables\PegawaiPotAfJointsTable;
use App\Models\PegawaiPotAfJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiPotAfJointResource extends Resource
{
    protected static ?string $model = PegawaiPotAfJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiPotAfJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiPotAfJointsTable::configure($table);
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
            'index' => ListPegawaiPotAfJoints::route('/'),
            'create' => CreatePegawaiPotAfJoint::route('/create'),
            'edit' => EditPegawaiPotAfJoint::route('/{record}/edit'),
        ];
    }
}
