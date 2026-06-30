<?php

namespace App\Filament\Resources\PegawaiSandingJoints;

use App\Filament\Resources\PegawaiSandingJoints\Pages\CreatePegawaiSandingJoint;
use App\Filament\Resources\PegawaiSandingJoints\Pages\EditPegawaiSandingJoint;
use App\Filament\Resources\PegawaiSandingJoints\Pages\ListPegawaiSandingJoints;
use App\Filament\Resources\PegawaiSandingJoints\Schemas\PegawaiSandingJointForm;
use App\Filament\Resources\PegawaiSandingJoints\Tables\PegawaiSandingJointsTable;
use App\Models\PegawaiSandingJoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiSandingJointResource extends Resource
{
    protected static ?string $model = PegawaiSandingJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiSandingJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiSandingJointsTable::configure($table);
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
            'index' => ListPegawaiSandingJoints::route('/'),
            'create' => CreatePegawaiSandingJoint::route('/create'),
            'edit' => EditPegawaiSandingJoint::route('/{record}/edit'),
        ];
    }
}
