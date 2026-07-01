<?php

namespace App\Filament\Resources\AnakAkuns;

use App\Filament\Resources\AnakAkuns\Pages\CreateAnakAkun;
use App\Filament\Resources\AnakAkuns\Pages\EditAnakAkun;
use App\Filament\Resources\AnakAkuns\Pages\ListAnakAkuns;
use App\Filament\Resources\AnakAkuns\Schemas\AnakAkunForm;
use App\Filament\Resources\AnakAkuns\Tables\AnakAkunsTable;
use App\Models\AnakAkun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AnakAkunResource extends Resource
{   
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = AnakAkun::class;

    protected static ?string $label = 'Anak Akun';

    protected static ?string $pluralLabel = 'Anak Akun';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Anak Akun';

    public static function form(Schema $schema): Schema
    {
        return AnakAkunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnakAkunsTable::configure($table);
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
            'index' => ListAnakAkuns::route('/'),
            'create' => CreateAnakAkun::route('/create'),
            'edit' => EditAnakAkun::route('/{record}/edit'),
        ];
    }
}
