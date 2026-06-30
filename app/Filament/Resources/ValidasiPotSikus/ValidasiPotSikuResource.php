<?php

namespace App\Filament\Resources\ValidasiPotSikus;

use App\Filament\Resources\ValidasiPotSikus\Pages\CreateValidasiPotSiku;
use App\Filament\Resources\ValidasiPotSikus\Pages\EditValidasiPotSiku;
use App\Filament\Resources\ValidasiPotSikus\Pages\ListValidasiPotSikus;
use App\Filament\Resources\ValidasiPotSikus\Schemas\ValidasiPotSikuForm;
use App\Filament\Resources\ValidasiPotSikus\Tables\ValidasiPotSikusTable;
use App\Models\ValidasiPotSiku;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiPotSikuResource extends Resource
{
    protected static ?string $model = ValidasiPotSiku::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiPotSikuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPotSikusTable::configure($table);
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
            'index' => ListValidasiPotSikus::route('/'),
            'create' => CreateValidasiPotSiku::route('/create'),
            'edit' => EditValidasiPotSiku::route('/{record}/edit'),
        ];
    }
}
