<?php

namespace App\Filament\Resources\ProduksiGuellotines;

use App\Filament\Resources\ProduksiGuellotines\Pages\CreateProduksiGuellotine;
use App\Filament\Resources\ProduksiGuellotines\Pages\EditProduksiGuellotine;
use App\Filament\Resources\ProduksiGuellotines\Pages\ListProduksiGuellotines;
use App\Filament\Resources\ProduksiGuellotines\Pages\ViewProduksiGuellotine;
use App\Filament\Resources\ProduksiGuellotines\Schemas\ProduksiGuellotineForm;
use App\Filament\Resources\ProduksiGuellotines\Schemas\ProduksiGuellotineInfoList;
use App\Filament\Resources\ProduksiGuellotines\Tables\ProduksiGuellotinesTable;
use App\Models\produksi_guellotine;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiGuellotineResource extends Resource
{
    protected static ?string $model = produksi_guellotine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?string $modelLabel = 'Produksi Guellotine';
    protected static ?string $pluralModelLabel = 'Produksi Guellotine';

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiGuellotineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiGuellotinesTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiGuellotineInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiGuellotineRelationManager::class,
            RelationManagers\HasilGuellotineRelationManager::class,
            RelationManagers\ValidasiGuellotineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiGuellotines::route('/'),
            'create' => CreateProduksiGuellotine::route('/create'),
            'view' => ViewProduksiGuellotine::route('/{record}'),
            'edit' => EditProduksiGuellotine::route('/{record}/edit'),
        ];
    }
}
