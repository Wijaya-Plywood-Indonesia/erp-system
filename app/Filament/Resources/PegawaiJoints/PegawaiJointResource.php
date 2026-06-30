<?php

namespace App\Filament\Resources\PegawaiJoints;

use App\Filament\Resources\PegawaiJoints\Pages\CreatePegawaiJoint;
use App\Filament\Resources\PegawaiJoints\Pages\EditPegawaiJoint;
use App\Filament\Resources\PegawaiJoints\Pages\ListPegawaiJoints;
use App\Filament\Resources\PegawaiJoints\Schemas\PegawaiJointForm;
use App\Filament\Resources\PegawaiJoints\Tables\PegawaiJointsTable;
use App\Models\PegawaiJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiJointResource extends Resource
{
    protected static ?string $model = PegawaiJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiJointsTable::configure($table);
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
            'index' => ListPegawaiJoints::route('/'),
            'create' => CreatePegawaiJoint::route('/create'),
            'edit' => EditPegawaiJoint::route('/{record}/edit'),
        ];
    }
}
