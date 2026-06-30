<?php

namespace App\Filament\Resources\ValidasiGuellotines;

use App\Filament\Resources\ValidasiGuellotines\Pages\CreateValidasiGuellotine;
use App\Filament\Resources\ValidasiGuellotines\Pages\EditValidasiGuellotine;
use App\Filament\Resources\ValidasiGuellotines\Pages\ListValidasiGuellotines;
use App\Filament\Resources\ValidasiGuellotines\Schemas\ValidasiGuellotineForm;
use App\Filament\Resources\ValidasiGuellotines\Tables\ValidasiGuellotinesTable;
use App\Models\validasi_guellotine;
use App\Models\ValidasiGuellotine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiGuellotineResource extends Resource
{
    protected static ?string $model = validasi_guellotine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiGuellotineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiGuellotinesTable::configure($table);
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
            'index' => ListValidasiGuellotines::route('/'),
            'create' => CreateValidasiGuellotine::route('/create'),
            'edit' => EditValidasiGuellotine::route('/{record}/edit'),
        ];
    }
}
