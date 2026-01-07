<?php

namespace App\Filament\Resources\ProduksiPotAfJoints;

use App\Filament\Resources\ProduksiPotAfJoints\Pages\CreateProduksiPotAfJoint;
use App\Filament\Resources\ProduksiPotAfJoints\Pages\EditProduksiPotAfJoint;
use App\Filament\Resources\ProduksiPotAfJoints\Pages\ListProduksiPotAfJoints;
use App\Filament\Resources\ProduksiPotAfJoints\Pages\ViewProduksiPotAfJoint;
use App\Filament\Resources\ProduksiPotAfJoints\Schemas\ProduksiPotAfJointForm;
use App\Filament\Resources\ProduksiPotAfJoints\Schemas\ProduksiPotAfJointInfoList;
use App\Filament\Resources\ProduksiPotAfJoints\Tables\ProduksiPotAfJointsTable;
use App\Models\ProduksiPotAfJoint;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiPotAfJointResource extends Resource
{
    protected static ?string $model = ProduksiPotAfJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiPotAfJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPotAfJointsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPotAfJointInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiPotAfJointRelationManager::class,
            RelationManagers\HasilPotAfJointRelationManager::class,
            RelationManagers\ValidasiPotAfJointRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPotAfJoints::route('/'),
            'create' => CreateProduksiPotAfJoint::route('/create'),
            'view' => ViewProduksiPotAfJoint::route('/{record}'),
            'edit' => EditProduksiPotAfJoint::route('/{record}/edit'),
        ];
    }
}
