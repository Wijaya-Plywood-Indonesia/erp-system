<?php

namespace App\Filament\Resources\ValidasiGrajiStiks;

use App\Filament\Resources\ValidasiGrajiStiks\Pages\CreateValidasiGrajiStik;
use App\Filament\Resources\ValidasiGrajiStiks\Pages\EditValidasiGrajiStik;
use App\Filament\Resources\ValidasiGrajiStiks\Pages\ListValidasiGrajiStiks;
use App\Filament\Resources\ValidasiGrajiStiks\Schemas\ValidasiGrajiStikForm;
use App\Filament\Resources\ValidasiGrajiStiks\Tables\ValidasiGrajiStiksTable;
use App\Models\ValidasiGrajiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiGrajiStikResource extends Resource
{
    protected static ?string $model = ValidasiGrajiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'ValidasiGrajiStik';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiGrajiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiGrajiStiksTable::configure($table);
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
            'index' => ListValidasiGrajiStiks::route('/'),
            'create' => CreateValidasiGrajiStik::route('/create'),
            'edit' => EditValidasiGrajiStik::route('/{record}/edit'),
        ];
    }
}
