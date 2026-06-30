<?php

namespace App\Filament\Resources\LainLains;

use App\Filament\Resources\LainLains\Pages\CreateLainLain;
use App\Filament\Resources\LainLains\Pages\EditLainLain;
use App\Filament\Resources\LainLains\Pages\ListLainLains;
use App\Filament\Resources\LainLains\Schemas\LainLainForm;
use App\Filament\Resources\LainLains\Tables\LainLainsTable;
use App\Models\LainLain;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LainLainResource extends Resource
{
    protected static ?string $model = LainLain::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Lain lain';
    protected static ?string $pluralModelLabel = 'Lain lain';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return LainLainForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LainLainsTable::configure($table);
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
            'index' => ListLainLains::route('/'),
            'create' => CreateLainLain::route('/create'),
            'edit' => EditLainLain::route('/{record}/edit'),
        ];
    }
}
