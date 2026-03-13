<?php

namespace App\Filament\Resources\StokVeneerKerings;

use App\Filament\Resources\StokVeneerKerings\Pages\CreateStokVeneerKering;
use App\Filament\Resources\StokVeneerKerings\Pages\EditStokVeneerKering;
use App\Filament\Resources\StokVeneerKerings\Pages\ListStokVeneerKerings;
use App\Filament\Resources\StokVeneerKerings\Pages\ViewStokVeneerKering;
use App\Filament\Resources\StokVeneerKerings\Schemas\StokVeneerKeringForm;
use App\Filament\Resources\StokVeneerKerings\Tables\StokVeneerKeringsTable;
use App\Models\StokVeneerKering;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StokVeneerKeringResource extends Resource
{
    protected static ?string $model = StokVeneerKering::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'HPP & Biaya';
    protected static ?string $navigationLabel = 'Stok Veneer Kering';
    protected static ?string $modelLabel = 'Stok Veneer Kering';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return StokVeneerKeringForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StokVeneerKeringsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStokVeneerKerings::route('/'),
            'create' => CreateStokVeneerKering::route('/create'),
            'view' => ViewStokVeneerKering::route('/{record}'),
            'edit' => EditStokVeneerKering::route('/{record}/edit'),

        ];
    }
}
