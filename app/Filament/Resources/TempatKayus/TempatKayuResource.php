<?php

namespace App\Filament\Resources\TempatKayus;

use App\Filament\Resources\TempatKayus\Pages\CreateTempatKayu;
use App\Filament\Resources\TempatKayus\Pages\EditTempatKayu;
use App\Filament\Resources\TempatKayus\Pages\ListTempatKayus;
use App\Filament\Resources\TempatKayus\Schemas\TempatKayuForm;
use App\Filament\Resources\TempatKayus\Tables\TempatKayusTable;
use App\Models\TempatKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TempatKayuResource extends Resource
{
    protected static ?string $model = TempatKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Kayu';

    public static function form(Schema $schema): Schema
    {
        return TempatKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TempatKayusTable::configure($table);
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
            'index' => ListTempatKayus::route('/'),
            'create' => CreateTempatKayu::route('/create'),
            'edit' => EditTempatKayu::route('/{record}/edit'),
        ];
    }
}
