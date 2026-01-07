<?php

namespace App\Filament\Resources\ProduksiJoints;

use App\Filament\Resources\ProduksiJoints\Pages\CreateProduksiJoint;
use App\Filament\Resources\ProduksiJoints\Pages\EditProduksiJoint;
use App\Filament\Resources\ProduksiJoints\Pages\ListProduksiJoints;
use App\Filament\Resources\ProduksiJoints\Pages\ViewProduksiJoint;
use App\Filament\Resources\ProduksiJoints\Schemas\ProduksiJointForm;
use App\Filament\Resources\ProduksiJoints\Schemas\ProduksiJointInfoList;
use App\Filament\Resources\ProduksiJoints\Tables\ProduksiJointsTable;
use App\Models\ProduksiJoint;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiJointResource extends Resource
{
    protected static ?string $model = ProduksiJoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiJointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiJointsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiJointInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiJointRelationManager::class,
            RelationManagers\ModalJointRelationManager::class,
            RelationManagers\HasilJointRelationManager::class,
            RelationManagers\BahanProduksiRelationManager::class,
            RelationManagers\ValidasiJointRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiJoints::route('/'),
            'create' => CreateProduksiJoint::route('/create'),
            'view' => ViewProduksiJoint::route('/{record}'),
            'edit' => EditProduksiJoint::route('/{record}/edit'),
        ];
    }
}
