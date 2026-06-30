<?php

namespace App\Filament\Resources\SupplierKayus;

use App\Filament\Resources\SupplierKayus\Pages\CreateSupplierKayu;
use App\Filament\Resources\SupplierKayus\Pages\EditSupplierKayu;
use App\Filament\Resources\SupplierKayus\Pages\ListSupplierKayus;
use App\Filament\Resources\SupplierKayus\Pages\ViewSupplierKayu;
use App\Filament\Resources\SupplierKayus\Schemas\SupplierKayuForm;
use App\Filament\Resources\SupplierKayus\Schemas\SupplierKayuInfolist;
use App\Filament\Resources\SupplierKayus\Tables\SupplierKayusTable;
use App\Models\SupplierKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class SupplierKayuResource extends Resource
{
    protected static ?string $model = SupplierKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return SupplierKayuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupplierKayuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupplierKayusTable::configure($table);
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
            'index' => ListSupplierKayus::route('/'),
            'create' => CreateSupplierKayu::route('/create'),
            'view' => ViewSupplierKayu::route('/{record}'),
            'edit' => EditSupplierKayu::route('/{record}/edit'),
        ];
    }
}
