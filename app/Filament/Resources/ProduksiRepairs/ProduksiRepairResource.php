<?php

namespace App\Filament\Resources\ProduksiRepairs;

use App\Filament\Resources\ProduksiRepairs\Pages\CreateProduksiRepair;
use App\Filament\Resources\ProduksiRepairs\Pages\EditProduksiRepair;
use App\Filament\Resources\ProduksiRepairs\Pages\ListProduksiRepairs;
use App\Filament\Resources\ProduksiRepairs\Pages\ViewProduksiRepair;
use App\Filament\Resources\ProduksiRepairs\RelationManagers\BahanPenolongRepairRelationManager;
use App\Filament\Resources\ProduksiRepairs\Schemas\ProduksiRepairForm;
use App\Filament\Resources\ProduksiRepairs\Schemas\ProduksiRepairInfolist;
use App\Filament\Resources\ProduksiRepairs\Tables\ProduksiRepairsTable;
use App\Models\ProduksiRepair;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

// Relation Managers
use App\Filament\Resources\ProduksiRepairs\RelationManagers\DetailRencanaPegawaiRelationManager;
use App\Filament\Resources\ProduksiRepairs\RelationManagers\RencanaRepairRelationManager;
use App\Filament\Resources\ProduksiRepairs\RelationManagers\HasilRepairRelationManager;
use App\Filament\Resources\ProduksiRepairs\RelationManagers\ModalRepairRelationManager;
use App\Filament\Resources\ProduksiRepairs\RelationManagers\ValidasiRepairRelationManager;
use App\Models\BahanPenolongRepair;

class ProduksiRepairResource extends Resource
{
    protected static ?string $model = ProduksiRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Repair';

    public static function form(Schema $schema): Schema
    {
        return ProduksiRepairForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiRepairInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiRepairsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ModalRepairRelationManager::class,
            DetailRencanaPegawaiRelationManager::class,
            RencanaRepairRelationManager::class,
            HasilRepairRelationManager::class,
            BahanPenolongRepairRelationManager::class,
            ValidasiRepairRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiRepairs::route('/'),
            'create' => CreateProduksiRepair::route('/create'),
            'view' => ViewProduksiRepair::route('/{record}'),
            'edit' => EditProduksiRepair::route('/{record}/edit'),
        ];
    }
}
