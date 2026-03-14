<?php

namespace App\Filament\Resources\OngkosProduksiDryers;

use App\Filament\Resources\OngkosProduksiDryers\Pages\CreateOngkosProduksiDryer;
use App\Filament\Resources\OngkosProduksiDryers\Pages\EditOngkosProduksiDryer;
use App\Filament\Resources\OngkosProduksiDryers\Pages\ListOngkosProduksiDryers;
use App\Filament\Resources\OngkosProduksiDryers\Pages\ViewOngkosProduksiDryer;
use App\Filament\Resources\OngkosProduksiDryers\Schemas\OngkosProduksiDryerForm;
use App\Filament\Resources\OngkosProduksiDryers\Tables\OngkosProduksiDryersTable;
use App\Models\OngkosProduksiDryer;
use App\Models\ProduksiPressDryer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OngkosProduksiDryerResource extends Resource
{
    protected static ?string $model = OngkosProduksiDryer::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;
    protected static string|UnitEnum|null $navigationGroup = 'HPP & Biaya';
    protected static ?string $navigationLabel = 'Ongkos Produksi Dryer';
    protected static ?string $modelLabel = 'Ongkos Produksi Dryer';
    protected static ?int $navigationSort = 1;
    public static function form(Schema $schema): Schema
    {
        return OngkosProduksiDryerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OngkosProduksiDryersTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('produksi')
            ->orderByDesc(
                ProduksiPressDryer::select('tanggal_produksi')
                    ->whereColumn('produksi_press_dryers.id', 'ongkos_produksi_dryers.id_produksi_dryer')
                    ->limit(1)
            );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOngkosProduksiDryers::route('/'),
            'create' => CreateOngkosProduksiDryer::route('/create'),
            'view' => ViewOngkosProduksiDryer::route('/{record}'),
            'edit' => EditOngkosProduksiDryer::route('/{record}/edit'),
        ];
    }
}