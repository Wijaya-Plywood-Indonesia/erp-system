<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks;

use App\Filament\Resources\ProduksiGrajiTripleks\Pages\CreateProduksiGrajiTriplek;
use App\Filament\Resources\ProduksiGrajiTripleks\Pages\EditProduksiGrajiTriplek;
use App\Filament\Resources\ProduksiGrajiTripleks\Pages\ListProduksiGrajiTripleks;
use App\Filament\Resources\ProduksiGrajiTripleks\Pages\ViewProduksiGrajiTriplek;
use App\Filament\Resources\ProduksiGrajiTripleks\Schemas\ProduksiGrajiTriplekForm;
use App\Filament\Resources\ProduksiGrajiTripleks\Schemas\ProduksiGrajiTriplekInfoList;
use App\Filament\Resources\ProduksiGrajiTripleks\Tables\ProduksiGrajiTripleksTable;
use App\Models\ProduksiGrajitriplek;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiGrajiTriplekResource extends Resource
{
    protected static ?string $model = ProduksiGrajiTriplek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Produksi Graji Triplek';
    protected static ?string $pluralModelLabel = 'Produksi Graji Triplek';

    public static function form(Schema $schema): Schema
    {
        return ProduksiGrajiTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiGrajiTripleksTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiGrajiTriplekInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiGrajiTriplekRelationManager::class,
            RelationManagers\MasukGrajiTriplekRelationManager::class,
            RelationManagers\HasilGrajiTriplekRelationManager::class,
            RelationManagers\ValidasiGrajiTriplekRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiGrajiTripleks::route('/'),
            'create' => CreateProduksiGrajiTriplek::route('/create'),
            'view' => ViewProduksiGrajiTriplek::route('/{record}'),
            'edit' => EditProduksiGrajiTriplek::route('/{record}/edit'),
        ];
    }
}
