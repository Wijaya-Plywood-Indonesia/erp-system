<?php

namespace App\Filament\Resources\HasilGrajiTripleks;

use App\Filament\Resources\HasilGrajiTripleks\Pages\CreateHasilGrajiTriplek;
use App\Filament\Resources\HasilGrajiTripleks\Pages\EditHasilGrajiTriplek;
use App\Filament\Resources\HasilGrajiTripleks\Pages\ListHasilGrajiTripleks;
use App\Filament\Resources\HasilGrajiTripleks\Schemas\HasilGrajiTriplekForm;
use App\Filament\Resources\HasilGrajiTripleks\Tables\HasilGrajiTripleksTable;
use App\Models\HasilGrajiTriplek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilGrajiTriplekResource extends Resource
{
    protected static ?string $model = HasilGrajiTriplek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'hasil';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilGrajiTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilGrajiTripleksTable::configure($table);
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
            'index' => ListHasilGrajiTripleks::route('/'),
            'create' => CreateHasilGrajiTriplek::route('/create'),
            'edit' => EditHasilGrajiTriplek::route('/{record}/edit'),
        ];
    }
}
