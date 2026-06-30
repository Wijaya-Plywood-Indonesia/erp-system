<?php

namespace App\Filament\Resources\ValidasiStiks;

use App\Filament\Resources\ValidasiStiks\Pages\CreateValidasiStik;
use App\Filament\Resources\ValidasiStiks\Pages\EditValidasiStik;
use App\Filament\Resources\ValidasiStiks\Pages\ListValidasiStiks;
use App\Filament\Resources\ValidasiStiks\Schemas\ValidasiStikForm;
use App\Filament\Resources\ValidasiStiks\Tables\ValidasiStiksTable;
use App\Models\ValidasiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ValidasiStikResource extends Resource
{
    protected static ?string $model = ValidasiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiStiksTable::configure($table);
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
            'index' => ListValidasiStiks::route('/'),
            'create' => CreateValidasiStik::route('/create'),
            'edit' => EditValidasiStik::route('/{record}/edit'),
        ];
    }
}
