<?php

namespace App\Filament\Resources\ProduksiSandingJoints;

use App\Filament\Resources\ProduksiSandingJoints\Pages\CreateProduksiSandingJoint;
use App\Filament\Resources\ProduksiSandingJoints\Pages\EditProduksiSandingJoint;
use App\Filament\Resources\ProduksiSandingJoints\Pages\ListProduksiSandingJoints;
use App\Filament\Resources\ProduksiSandingJoints\Pages\ViewProduksiSandingJoint;
use App\Filament\Resources\ProduksiSandingJoints\Schemas\ProduksiSandingJointForm;
use App\Filament\Resources\ProduksiSandingJoints\Schemas\ProduksiSandingJointInfoList;
use App\Filament\Resources\ProduksiSandingJoints\Tables\ProduksiSandingJointsTable;
use App\Models\ProduksiSandingJoint;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiSandingJointResource extends Resource
{
    protected static ?string $model = ProduksiSandingJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiSandingJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiSandingJointsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiSandingJointInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiSandingJointRelationManager::class,
            RelationManagers\HasilSandingJointRelationManager::class,
            RelationManagers\ValidasiSandingJointRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiSandingJoints::route('/'),
            'create' => CreateProduksiSandingJoint::route('/create'),
            'view' => ViewProduksiSandingJoint::route('/{record}'),
            'edit' => EditProduksiSandingJoint::route('/{record}/edit'),
        ];
    }
}
