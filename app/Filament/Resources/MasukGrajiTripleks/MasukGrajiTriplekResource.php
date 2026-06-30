<?php

namespace App\Filament\Resources\MasukGrajiTripleks;

use App\Filament\Resources\MasukGrajiTripleks\Pages\CreateMasukGrajiTriplek;
use App\Filament\Resources\MasukGrajiTripleks\Pages\EditMasukGrajiTriplek;
use App\Filament\Resources\MasukGrajiTripleks\Pages\ListMasukGrajiTripleks;
use App\Filament\Resources\MasukGrajiTripleks\Schemas\MasukGrajiTriplekForm;
use App\Filament\Resources\MasukGrajiTripleks\Tables\MasukGrajiTripleksTable;
use App\Models\MasukGrajiTriplek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class MasukGrajiTriplekResource extends Resource
{
    protected static ?string $model = MasukGrajiTriplek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'masuk';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return MasukGrajiTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasukGrajiTripleksTable::configure($table);
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
            'index' => ListMasukGrajiTripleks::route('/'),
            'create' => CreateMasukGrajiTriplek::route('/create'),
            'edit' => EditMasukGrajiTriplek::route('/{record}/edit'),
        ];
    }
}
