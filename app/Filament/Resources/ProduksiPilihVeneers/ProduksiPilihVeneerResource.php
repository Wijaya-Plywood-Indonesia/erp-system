<?php

namespace App\Filament\Resources\ProduksiPilihVeneers;

use App\Filament\Resources\ProduksiPilihVeneers\Pages\CreateProduksiPilihVeneer;
use App\Filament\Resources\ProduksiPilihVeneers\Pages\EditProduksiPilihVeneer;
use App\Filament\Resources\ProduksiPilihVeneers\Pages\ListProduksiPilihVeneers;
use App\Filament\Resources\ProduksiPilihVeneers\Pages\ViewProduksiPilihVeneer;
use App\Filament\Resources\ProduksiPilihVeneers\RelationManagers\HasilPilihVeneerRelationManager;
use App\Filament\Resources\ProduksiPilihVeneers\RelationManagers\ModalPilihVeneerRelationManager;
use App\Filament\Resources\ProduksiPilihVeneers\RelationManagers\PegawaiPilihVeneerRelationManager;
use App\Filament\Resources\ProduksiPilihVeneers\RelationManagers\ValidasiPilihVeneerRelationManager;
use App\Filament\Resources\ProduksiPilihVeneers\Schemas\ProduksiPilihVeneerForm;
use App\Filament\Resources\ProduksiPilihVeneers\Schemas\ProduksiPilihVeneerInfoList;
use App\Filament\Resources\ProduksiPilihVeneers\Tables\ProduksiPilihVeneersTable;
use App\Models\ProduksiPilihVeneer;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiPilihVeneerResource extends Resource
{
    protected static ?string $model = ProduksiPilihVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiPilihVeneerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPilihVeneersTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPilihVeneerInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiPilihVeneerRelationManager::class,
            ModalPilihVeneerRelationManager::class,
            HasilPilihVeneerRelationManager::class,
            ValidasiPilihVeneerRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPilihVeneers::route('/'),
            'create' => CreateProduksiPilihVeneer::route('/create'),
            'view' => ViewProduksiPilihVeneer::route('/{record}'),
            'edit' => EditProduksiPilihVeneer::route('/{record}/edit'),
        ];
    }
}
