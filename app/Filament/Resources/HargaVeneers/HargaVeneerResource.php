<?php

namespace App\Filament\Resources\HargaVeneers;

use App\Filament\Resources\HargaVeneers\Pages\CreateHargaVeneer;
use App\Filament\Resources\HargaVeneers\Pages\EditHargaVeneer;
use App\Filament\Resources\HargaVeneers\Pages\ListHargaVeneers;
use App\Filament\Resources\HargaVeneers\Pages\ViewHargaVeneer;
use App\Filament\Resources\HargaVeneers\Schemas\HargaVeneerForm;
use App\Filament\Resources\HargaVeneers\Schemas\HargaVeneerInfolist;
use App\Filament\Resources\HargaVeneers\Tables\HargaVeneersTable;
use App\Models\HargaVeneer;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HargaVeneerResource extends Resource
{
    protected static ?string $model = HargaVeneer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';
    protected static ?string $navigationLabel = 'Harga Veneer';
    protected static ?string $pluralModelLabel = 'Harga Veneer';
    protected static ?string $modelLabel = 'Harga Veneer';

    public static function form(Schema $schema): Schema
    {
        return HargaVeneerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HargaVeneerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaVeneersTable::configure($table);
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
            'index' => ListHargaVeneers::route('/'),
            'create' => CreateHargaVeneer::route('/create'),
            'view' => ViewHargaVeneer::route('/{record}'),
            'edit' => EditHargaVeneer::route('/{record}/edit'),
        ];
    }
}
