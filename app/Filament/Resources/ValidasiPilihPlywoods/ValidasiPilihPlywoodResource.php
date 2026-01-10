<?php

namespace App\Filament\Resources\ValidasiPilihPlywoods;

use App\Filament\Resources\ValidasiPilihPlywoods\Pages\CreateValidasiPilihPlywood;
use App\Filament\Resources\ValidasiPilihPlywoods\Pages\EditValidasiPilihPlywood;
use App\Filament\Resources\ValidasiPilihPlywoods\Pages\ListValidasiPilihPlywoods;
use App\Filament\Resources\ValidasiPilihPlywoods\Schemas\ValidasiPilihPlywoodForm;
use App\Filament\Resources\ValidasiPilihPlywoods\Tables\ValidasiPilihPlywoodsTable;
use App\Models\ValidasiPilihPlywood;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiPilihPlywoodResource extends Resource
{
    protected static ?string $model = ValidasiPilihPlywood::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ValidasiPilihPlywoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPilihPlywoodsTable::configure($table);
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
            'index' => ListValidasiPilihPlywoods::route('/'),
            'create' => CreateValidasiPilihPlywood::route('/create'),
            'edit' => EditValidasiPilihPlywood::route('/{record}/edit'),
        ];
    }
}
