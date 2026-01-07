<?php

namespace App\Filament\Resources\HasilPilihPlywoods;

use App\Filament\Resources\HasilPilihPlywoods\Pages\CreateHasilPilihPlywood;
use App\Filament\Resources\HasilPilihPlywoods\Pages\EditHasilPilihPlywood;
use App\Filament\Resources\HasilPilihPlywoods\Pages\ListHasilPilihPlywoods;
use App\Filament\Resources\HasilPilihPlywoods\Schemas\HasilPilihPlywoodForm;
use App\Filament\Resources\HasilPilihPlywoods\Tables\HasilPilihPlywoodsTable;
use App\Models\HasilPilihPlywood;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilPilihPlywoodResource extends Resource
{
    protected static ?string $model = HasilPilihPlywood::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return HasilPilihPlywoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilPilihPlywoodsTable::configure($table);
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
            'index' => ListHasilPilihPlywoods::route('/'),
            'create' => CreateHasilPilihPlywood::route('/create'),
            'edit' => EditHasilPilihPlywood::route('/{record}/edit'),
        ];
    }
}
