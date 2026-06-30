<?php

namespace App\Filament\Resources\ValidasiGrajiTripleks;

use App\Filament\Resources\ValidasiGrajiTripleks\Pages\CreateValidasiGrajiTriplek;
use App\Filament\Resources\ValidasiGrajiTripleks\Pages\EditValidasiGrajiTriplek;
use App\Filament\Resources\ValidasiGrajiTripleks\Pages\ListValidasiGrajiTripleks;
use App\Filament\Resources\ValidasiGrajiTripleks\Schemas\ValidasiGrajiTriplekForm;
use App\Filament\Resources\ValidasiGrajiTripleks\Tables\ValidasiGrajiTripleksTable;
use App\Models\ValidasiGrajiTriplek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiGrajiTriplekResource extends Resource
{
    protected static ?string $model = ValidasiGrajiTriplek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiGrajiTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiGrajiTripleksTable::configure($table);
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
            'index' => ListValidasiGrajiTripleks::route('/'),
            'create' => CreateValidasiGrajiTriplek::route('/create'),
            'edit' => EditValidasiGrajiTriplek::route('/{record}/edit'),
        ];
    }
}
