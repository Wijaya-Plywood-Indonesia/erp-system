<?php

namespace App\Filament\Resources\ValidasiDempuls;

use App\Filament\Resources\ValidasiDempuls\Pages\CreateValidasiDempul;
use App\Filament\Resources\ValidasiDempuls\Pages\EditValidasiDempul;
use App\Filament\Resources\ValidasiDempuls\Pages\ListValidasiDempuls;
use App\Filament\Resources\ValidasiDempuls\Schemas\ValidasiDempulForm;
use App\Filament\Resources\ValidasiDempuls\Tables\ValidasiDempulsTable;
use App\Models\ValidasiDempul;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiDempulResource extends Resource
{
    protected static ?string $model = ValidasiDempul::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return ValidasiDempulForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiDempulsTable::configure($table);
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
            'index' => ListValidasiDempuls::route('/'),
            'create' => CreateValidasiDempul::route('/create'),
            'edit' => EditValidasiDempul::route('/{record}/edit'),
        ];
    }
}
