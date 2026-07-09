<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus;

use App\Filament\Resources\ProduksiTerimaGudangSatus\Pages\CreateProduksiTerimaGudangSatu;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Pages\EditProduksiTerimaGudangSatu;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Pages\ListProduksiTerimaGudangSatus;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Pages\ViewProduksiTerimaGudangSatu;
use App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers\BahanTerimaGudangSatuRelationManager;
use App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers\HasilProduksiTerimaGudangSatuRelationManager;
use App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers\PegawaiTerimaGudangSatuRelationManager;
use App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers\SerahTerimaGudangSatuRelationManager;
use App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers\ValidasiTerimaGudangSatuRelationManager;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Schemas\ProduksiTerimaGudangSatuForm;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Schemas\ProduksiTerimaGudangSatuInfolist;
use App\Filament\Resources\ProduksiTerimaGudangSatus\Tables\ProduksiTerimaGudangSatusTable;
use App\Models\ProduksiTerimaGudangSatu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduksiTerimaGudangSatuResource extends Resource
{
    protected static ?string $model = ProduksiTerimaGudangSatu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Finishing';

    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Terima Gudang Satu';

    protected static ?string $pluralLabel = 'Terima Gudang Satu';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return ProduksiTerimaGudangSatuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiTerimaGudangSatusTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiTerimaGudangSatuInfolist::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            SerahTerimaGudangSatuRelationManager::class,
            BahanTerimaGudangSatuRelationManager::class,
            PegawaiTerimaGudangSatuRelationManager::class,
            HasilProduksiTerimaGudangSatuRelationManager::class,
            ValidasiTerimaGudangSatuRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiTerimaGudangSatus::route('/'),
            'create' => CreateProduksiTerimaGudangSatu::route('/create'),
            'view' => ViewProduksiTerimaGudangSatu::route('/{record}'),
            'edit' => EditProduksiTerimaGudangSatu::route('/{record}/edit'),
        ];
    }
}
