<?php

namespace App\Filament\Resources\ValidasiPotJeleks;

use App\Filament\Resources\ValidasiPotJeleks\Pages\CreateValidasiPotJelek;
use App\Filament\Resources\ValidasiPotJeleks\Pages\EditValidasiPotJelek;
use App\Filament\Resources\ValidasiPotJeleks\Pages\ListValidasiPotJeleks;
use App\Filament\Resources\ValidasiPotJeleks\Schemas\ValidasiPotJelekForm;
use App\Filament\Resources\ValidasiPotJeleks\Tables\ValidasiPotJeleksTable;
use App\Models\ValidasiPotJelek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiPotJelekResource extends Resource
{
    protected static ?string $model = ValidasiPotJelek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiPotJelekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiPotJeleksTable::configure($table);
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
            'index' => ListValidasiPotJeleks::route('/'),
            'create' => CreateValidasiPotJelek::route('/create'),
            'edit' => EditValidasiPotJelek::route('/{record}/edit'),
        ];
    }
}
