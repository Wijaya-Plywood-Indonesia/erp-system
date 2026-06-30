<?php

namespace App\Filament\Resources\ValidasiNyusups;

use App\Filament\Resources\ValidasiNyusups\Pages\CreateValidasiNyusup;
use App\Filament\Resources\ValidasiNyusups\Pages\EditValidasiNyusup;
use App\Filament\Resources\ValidasiNyusups\Pages\ListValidasiNyusups;
use App\Filament\Resources\ValidasiNyusups\Schemas\ValidasiNyusupForm;
use App\Filament\Resources\ValidasiNyusups\Tables\ValidasiNyusupsTable;
use App\Models\ValidasiNyusup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiNyusupResource extends Resource
{
    protected static ?string $model = ValidasiNyusup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiNyusupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiNyusupsTable::configure($table);
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
            'index' => ListValidasiNyusups::route('/'),
            'create' => CreateValidasiNyusup::route('/create'),
            'edit' => EditValidasiNyusup::route('/{record}/edit'),
        ];
    }
}
