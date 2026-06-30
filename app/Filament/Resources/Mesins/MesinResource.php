<?php

namespace App\Filament\Resources\Mesins;

use App\Filament\Resources\Mesins\Pages\CreateMesin;
use App\Filament\Resources\Mesins\Pages\EditMesin;
use App\Filament\Resources\Mesins\Pages\ListMesins;
use App\Filament\Resources\Mesins\Pages\ViewMesin;
use App\Filament\Resources\Mesins\Schemas\MesinForm;
use App\Filament\Resources\Mesins\Schemas\MesinInfolist;
use App\Filament\Resources\Mesins\Tables\MesinsTable;
use App\Models\Mesin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MesinResource extends Resource
{
    protected static ?string $model = Mesin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;
    //Ngelompokin.
    protected static string|UnitEnum|null $navigationGroup = 'Master';

    public static function form(Schema $schema): Schema
    {
        return MesinForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MesinInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MesinsTable::configure($table);
    }
    //ngurutin
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->latest('created_at');
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
            'index' => ListMesins::route('/'),
            'create' => CreateMesin::route('/create'),
            'view' => ViewMesin::route('/{record}'),
            'edit' => EditMesin::route('/{record}/edit'),
        ];
    }
}
