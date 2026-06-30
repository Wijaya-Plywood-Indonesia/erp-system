<?php

namespace App\Filament\Resources\Ukurans;

use App\Filament\Resources\Ukurans\Pages\CreateUkuran;
use App\Filament\Resources\Ukurans\Pages\EditUkuran;
use App\Filament\Resources\Ukurans\Pages\ListUkurans;
use App\Filament\Resources\Ukurans\Pages\ViewUkuran;
use App\Filament\Resources\Ukurans\Schemas\UkuranForm;
use App\Filament\Resources\Ukurans\Schemas\UkuranInfolist;
use App\Filament\Resources\Ukurans\Tables\UkuransTable;
use App\Models\Ukuran;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class UkuranResource extends Resource
{
    protected static ?string $model = Ukuran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsPointingOut;
    //Ngelompokin.
    protected static string|UnitEnum|null $navigationGroup = 'Master';
    public static function form(Schema $schema): Schema
    {
        return UkuranForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return UkuranInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UkuransTable::configure($table);
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
            'index' => ListUkurans::route('/'),
            'create' => CreateUkuran::route('/create'),
            'view' => ViewUkuran::route('/{record}'),
            'edit' => EditUkuran::route('/{record}/edit'),
        ];
    }
}
