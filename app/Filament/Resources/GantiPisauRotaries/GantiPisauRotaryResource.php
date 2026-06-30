<?php

namespace App\Filament\Resources\GantiPisauRotaries;

use App\Filament\Resources\GantiPisauRotaries\Pages\CreateGantiPisauRotary;
use App\Filament\Resources\GantiPisauRotaries\Pages\EditGantiPisauRotary;
use App\Filament\Resources\GantiPisauRotaries\Pages\ListGantiPisauRotaries;
use App\Filament\Resources\GantiPisauRotaries\Schemas\GantiPisauRotaryForm;
use App\Filament\Resources\GantiPisauRotaries\Tables\GantiPisauRotariesTable;
use App\Models\GantiPisauRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GantiPisauRotaryResource extends Resource
{
    protected static ?string $model = GantiPisauRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;
    //grubping
    protected static string|UnitEnum|null $navigationGroup = 'Rotary';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?int $navigationSort = 5;
    //ngurutin
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
    }

    public static function form(Schema $schema): Schema
    {
        return GantiPisauRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GantiPisauRotariesTable::configure($table);
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
            'index' => ListGantiPisauRotaries::route('/'),
            'create' => CreateGantiPisauRotary::route('/create'),
            'edit' => EditGantiPisauRotary::route('/{record}/edit'),
        ];
    }
}
