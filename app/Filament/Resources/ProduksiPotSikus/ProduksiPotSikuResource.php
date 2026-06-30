<?php

namespace App\Filament\Resources\ProduksiPotSikus;

use App\Filament\Resources\ProduksiPotSikus\Pages\CreateProduksiPotSiku;
use App\Filament\Resources\ProduksiPotSikus\Pages\EditProduksiPotSiku;
use App\Filament\Resources\ProduksiPotSikus\Pages\ListProduksiPotSikus;
use App\Filament\Resources\ProduksiPotSikus\Pages\ViewProduksiPotSiku;
use App\Filament\Resources\ProduksiPotSikus\Schemas\ProduksiPotSikuForm;
use App\Filament\Resources\ProduksiPotSikus\Schemas\ProduksiPotSikuInfoList;
use App\Filament\Resources\ProduksiPotSikus\Tables\ProduksiPotSikusTable;
use App\Models\ProduksiPotSiku;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiPotSikuResource extends Resource
{
    protected static ?string $model = ProduksiPotSiku::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiPotSikuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPotSikusTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPotSikuInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiPotSikuRelationManager::class,
            RelationManagers\DetailBarangDikerjakanPotSikuRelationManager::class,
            RelationManagers\ValidasiPotSikuRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPotSikus::route('/'),
            'create' => CreateProduksiPotSiku::route('/create'),
            'view' => ViewProduksiPotSiku::route('/{record}'),
            'edit' => EditProduksiPotSiku::route('/{record}/edit'),
        ];
    }
}
