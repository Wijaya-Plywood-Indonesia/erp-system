<?php

namespace App\Filament\Resources\ProduksiHotPresses;

use App\Filament\Resources\ProduksiHotPresses\Pages\CreateProduksiHotPress;
use App\Filament\Resources\ProduksiHotPresses\Pages\EditProduksiHotPress;
use App\Filament\Resources\ProduksiHotPresses\Pages\ListProduksiHotPresses;
use App\Filament\Resources\ProduksiHotPresses\Pages\ViewProduksiHotPress;
use App\Filament\Resources\ProduksiHotPresses\Schemas\ProduksiHotPressForm;
use App\Filament\Resources\ProduksiHotPresses\Schemas\ProduksiHotPressInfoList;
use App\Filament\Resources\ProduksiHotPresses\Tables\ProduksiHotPressesTable;
use App\Filament\Resources\ProduksiHotPresses\Schemas\ProduksiStikInfoList;
use App\Models\ProduksiHp;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduksiHotPressResource extends Resource
{
    protected static ?string $model = ProduksiHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Hot Press';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Produksi Hot Press';
    protected static ?string $pluralModelLabel = 'Produksi Hot Press';

    public static function form(Schema $schema): Schema
    {
        return ProduksiHotPressForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiHotPressesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiHotPressInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RencanaKerjaHpRelationManager::class,
            RelationManagers\DetailPegawaiHpRelationManager::class,
            RelationManagers\VeneerBahanHpRelationManager::class,
            RelationManagers\PlatformBahanHpRelationManager::class,
            RelationManagers\BahanHotPressRelationManager::class,
            RelationManagers\PlatformHasilHpRelationManager::class,
            RelationManagers\TriplekHasilHpRelationManager::class,
            RelationManagers\BahanPenolongHpRelationManager::class,
            RelationManagers\ValidasiHpRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiHotPresses::route('/'),
            'create' => CreateProduksiHotPress::route('/create'),
            'view' => ViewProduksiHotPress::route('/{record}'),
            'edit' => EditProduksiHotPress::route('/{record}/edit'),
        ];
    }
}
