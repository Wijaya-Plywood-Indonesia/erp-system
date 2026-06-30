<?php

namespace App\Filament\Resources\HasilGuellotines;

use App\Filament\Resources\HasilGuellotines\Pages\CreateHasilGuellotine;
use App\Filament\Resources\HasilGuellotines\Pages\EditHasilGuellotine;
use App\Filament\Resources\HasilGuellotines\Pages\ListHasilGuellotines;
use App\Filament\Resources\HasilGuellotines\Schemas\HasilGuellotineForm;
use App\Filament\Resources\HasilGuellotines\Tables\HasilGuellotinesTable;
use App\Models\hasil_guellotine;
use App\Models\HasilGuellotine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilGuellotineResource extends Resource
{
    protected static ?string $model = hasil_guellotine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilGuellotineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilGuellotinesTable::configure($table);
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
            'index' => ListHasilGuellotines::route('/'),
            'create' => CreateHasilGuellotine::route('/create'),
            'edit' => EditHasilGuellotine::route('/{record}/edit'),
        ];
    }
}
