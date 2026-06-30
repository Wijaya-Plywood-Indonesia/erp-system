<?php

namespace App\Filament\Resources\ValidasiPressDryers;

use App\Filament\Resources\ValidasiPressDryers\Pages\CreateValidasiPressDryer;
use App\Filament\Resources\ValidasiPressDryers\Pages\EditValidasiPressDryer;
use App\Filament\Resources\ValidasiPressDryers\Pages\ListValidasiPressDryers;
use App\Filament\Resources\ValidasiPressDryers\Schemas\ValidasiPressDryerForm;
use App\Filament\Resources\ValidasiPressDryers\Tables\ValidasiPressDryersTable;
use App\Models\ValidasiPressDryer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ValidasiPressDryerResource extends Resource
{
    protected static ?string $model = ValidasiPressDryer::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;



    public static function form(Schema $schema): Schema
    {
        return ValidasiPressDryerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPressDryersTable::configure($table);
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
            'index' => ListValidasiPressDryers::route('/'),
            'create' => CreateValidasiPressDryer::route('/create'),
            'edit' => EditValidasiPressDryer::route('/{record}/edit'),
        ];
    }
}
