<?php

namespace App\Filament\Resources\ProduksiStiks;

use App\Filament\Resources\ProduksiStiks\Pages\CreateProduksiStik;
use App\Filament\Resources\ProduksiStiks\Pages\EditProduksiStik;
use App\Filament\Resources\ProduksiStiks\Pages\ListProduksiStiks;
use App\Filament\Resources\ProduksiStiks\Pages\ViewProduksiStik;
use App\Filament\Resources\ProduksiStiks\Schemas\ProduksiStikForm;
use App\Filament\Resources\ProduksiStiks\Tables\ProduksiStiksTable;
use App\Filament\Resources\ProduksiStiks\Schemas\ProduksiStikInfoList;
use App\Models\ProduksiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProduksiStikResource extends Resource
{
    protected static ?string $model = ProduksiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'Stik';

    public static function form(Schema $schema): Schema
    {
        return ProduksiStikForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiStikInfoList::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiStiksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DetailPegawaiStikRelationManager::class,
            RelationManagers\DetailMasukStikRelationManager::class,
            RelationManagers\DetailHasilStikRelationManager::class,
            RelationManagers\ValidasiStikRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiStiks::route('/'),
            'create' => CreateProduksiStik::route('/create'),
            'view' => ViewProduksiStik::route('/{record}'),
            'edit' => EditProduksiStik::route('/{record}/edit'),
        ];
    }
}
