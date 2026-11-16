<?php

namespace App\Filament\Resources\Penjualans;

use App\Filament\Resources\Penjualans\Pages\CreatePenjualan;
use App\Filament\Resources\Penjualans\Pages\EditPenjualan;
use App\Filament\Resources\Penjualans\Pages\ListPenjualans;
use App\Filament\Resources\Penjualans\RelationManagers\DetailPenjualansRelationManager;
use App\Filament\Resources\Penjualans\Schemas\PenjualanForm;
use App\Filament\Resources\Penjualans\Tables\PenjualansTable;
use App\Models\Penjualan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PenjualanResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $modelLabel = 'Penjualan';

    protected static ?string $pluralModelLabel = 'Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PenjualanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PenjualansTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DetailPenjualansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPenjualans::route('/'),
            'create' => CreatePenjualan::route('/create'),
            'edit'   => EditPenjualan::route('/{record}/edit'),
        ];
    }
}
