<?php

namespace App\Filament\Resources\ProduksiPalets;

use App\Filament\Resources\ProduksiPalets\Pages\CreateProduksiPalet;
use App\Filament\Resources\ProduksiPalets\Pages\EditProduksiPalet;
use App\Filament\Resources\ProduksiPalets\Pages\ListProduksiPalets;
use App\Filament\Resources\ProduksiPalets\Pages\ViewProduksiPalet;
use App\Filament\Resources\ProduksiPalets\RelationManagers\HasilProduksiPaletsRelationManager;
use App\Filament\Resources\ProduksiPalets\RelationManagers\PegawaiPaletsRelationManager;
use App\Filament\Resources\ProduksiPalets\RelationManagers\ValidasiProduksiPaletsRelationManager;
use App\Filament\Resources\ProduksiPalets\Schemas\ProduksiPaletForm;
use App\Filament\Resources\ProduksiPalets\Schemas\ProduksiPaletInfolist;
use App\Filament\Resources\ProduksiPalets\Tables\ProduksiPaletsTable;
use App\Models\ProduksiPalet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduksiPaletResource extends Resource
{
    protected static ?string $model = ProduksiPalet::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Rotary';

    protected static ?string $recordTitleAttribute = 'ProduksiPalet';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ProduksiPaletForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiPaletInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiPaletsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PegawaiPaletsRelationManager::class,
            HasilProduksiPaletsRelationManager::class,
            ValidasiProduksiPaletsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiPalets::route('/'),
            'create' => CreateProduksiPalet::route('/create'),
            'view' => ViewProduksiPalet::route('/{record}'),
            'edit' => EditProduksiPalet::route('/{record}/edit'),
        ];
    }
}
