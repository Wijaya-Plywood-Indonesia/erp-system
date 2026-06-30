<?php

namespace App\Filament\Resources\Komposisis;

use App\Filament\Resources\Komposisis\Pages\CreateKomposisi;
use App\Filament\Resources\Komposisis\Pages\EditKomposisi;
use App\Filament\Resources\Komposisis\Pages\ListKomposisis;
use App\Filament\Resources\Komposisis\Schemas\KomposisiForm;
use App\Filament\Resources\Komposisis\Tables\KomposisisTable;
use App\Models\Komposisi;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KomposisiResource extends Resource
{
    protected static ?string $model = Komposisi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'komposisi';

    public static function form(Schema $schema): Schema
    {
        return KomposisiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KomposisisTable::configure($table);
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
            'index' => ListKomposisis::route('/'),
            'create' => CreateKomposisi::route('/create'),
            'edit' => EditKomposisi::route('/{record}/edit'),
        ];
    }
}
