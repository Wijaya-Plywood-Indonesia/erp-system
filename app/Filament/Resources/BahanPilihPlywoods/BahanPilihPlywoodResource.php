<?php

namespace App\Filament\Resources\BahanPilihPlywoods;

use App\Filament\Resources\BahanPilihPlywoods\Pages\CreateBahanPilihPlywood;
use App\Filament\Resources\BahanPilihPlywoods\Pages\EditBahanPilihPlywood;
use App\Filament\Resources\BahanPilihPlywoods\Pages\ListBahanPilihPlywoods;
use App\Filament\Resources\BahanPilihPlywoods\Schemas\BahanPilihPlywoodForm;
use App\Filament\Resources\BahanPilihPlywoods\Tables\BahanPilihPlywoodsTable;
use App\Models\BahanPilihPlywood;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanPilihPlywoodResource extends Resource
{
    protected static ?string $model = BahanPilihPlywood::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BahanPilihPlywoodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanPilihPlywoodsTable::configure($table);
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
            'index' => ListBahanPilihPlywoods::route('/'),
            'create' => CreateBahanPilihPlywood::route('/create'),
            'edit' => EditBahanPilihPlywood::route('/{record}/edit'),
        ];
    }
}
