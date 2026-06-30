<?php

namespace App\Filament\Resources\DokumenKayus;

use App\Filament\Resources\DokumenKayus\Pages\CreateDokumenKayu;
use App\Filament\Resources\DokumenKayus\Pages\EditDokumenKayu;
use App\Filament\Resources\DokumenKayus\Pages\ListDokumenKayus;
use App\Filament\Resources\DokumenKayus\Pages\ViewDokumenKayu;
use App\Filament\Resources\DokumenKayus\Schemas\DokumenKayuForm;
use App\Filament\Resources\DokumenKayus\Schemas\DokumenKayuInfolist;
use App\Filament\Resources\DokumenKayus\Tables\DokumenKayusTable;
use App\Models\DokumenKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DokumenKayuResource extends Resource
{
    protected static ?string $model = DokumenKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return DokumenKayuForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DokumenKayuInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DokumenKayusTable::configure($table);
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
            'index' => ListDokumenKayus::route('/'),
            'create' => CreateDokumenKayu::route('/create'),
            'view' => ViewDokumenKayu::route('/{record}'),
            'edit' => EditDokumenKayu::route('/{record}/edit'),
        ];
    }
}
