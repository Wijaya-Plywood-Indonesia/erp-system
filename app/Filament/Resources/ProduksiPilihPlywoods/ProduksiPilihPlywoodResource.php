<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods;

use App\Filament\Resources\ProduksiPilihPlywoodResource\Widgets\ProduksiPilihPlywoodSummaryWidget;
use App\Filament\Resources\ProduksiPilihPlywoods\Pages\CreateProduksiPilihPlywood;
use App\Filament\Resources\ProduksiPilihPlywoods\Pages\EditProduksiPilihPlywood;
use App\Filament\Resources\ProduksiPilihPlywoods\Pages\ListProduksiPilihPlywoods;
use App\Filament\Resources\ProduksiPilihPlywoods\Pages\ViewProduksiPilihPlywood;
use App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers\BahanPilihPlywoodRelationManager;
use App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers\HasilPilihPlywoodRelationManager;
use App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers\ListPekerjaanMenumpukRelationManager;
use App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers\PegawaiPilihPlywoodRelationManager;
use App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers\ValidasiPilihPlywoodRelationManager;
use App\Filament\Resources\ProduksiPilihPlywoods\Schemas\ProduksiPilihPlywoodForm;
use App\Filament\Resources\ProduksiPilihPlywoods\Schemas\ProduksiPilihPlywoodInfolist;
use App\Filament\Resources\ProduksiPilihPlywoods\Tables\ProduksiPilihPlywoodsTable;
use App\Models\ProduksiPilihPlywood;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiPilihPlywoodResource extends Resource
{
    protected static ?string $model = ProduksiPilihPlywood::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'no';


    public static function form(Schema $schema): Schema
    {
        return ProduksiPilihPlywoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPilihPlywoodsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPilihPlywoodInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            BahanPilihPlywoodRelationManager::class,
            HasilPilihPlywoodRelationManager::class,
            ValidasiPilihPlywoodRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPilihPlywoods::route('/'),
            'create' => CreateProduksiPilihPlywood::route('/create'),
            'view' => ViewProduksiPilihPlywood::route('/{record}'),
            'edit' => EditProduksiPilihPlywood::route('/{record}/edit'),
        ];
    }
}
