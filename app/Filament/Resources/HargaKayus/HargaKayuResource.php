<?php

namespace App\Filament\Resources\HargaKayus;

use App\Filament\Resources\HargaKayus\Pages\CreateHargaKayu;
use App\Filament\Resources\HargaKayus\Pages\EditHargaKayu;
use App\Filament\Resources\HargaKayus\Pages\ListHargaKayus;
use App\Filament\Resources\HargaKayus\Pages\ViewHargaKayu;
use App\Filament\Resources\HargaKayus\Schemas\HargaKayuForm;
use App\Filament\Resources\HargaKayus\Schemas\HargaKayuInfolist;
use App\Filament\Resources\HargaKayus\Tables\HargaKayusTable;
use App\Models\HargaKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HargaKayuResource extends Resource
{
    protected static ?string $model = HargaKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return HargaKayuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return HargaKayuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaKayusTable::configure($table);
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
            'index' => ListHargaKayus::route('/'),
            'create' => CreateHargaKayu::route('/create'),
            'view' => ViewHargaKayu::route('/{record}'),
            'edit' => EditHargaKayu::route('/{record}/edit'),
        ];
    }
}
