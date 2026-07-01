<?php

namespace App\Filament\Resources\SubAnakAkuns;

use App\Filament\Resources\SubAnakAkuns\Pages\CreateSubAnakAkun;
use App\Filament\Resources\SubAnakAkuns\Pages\EditSubAnakAkun;
use App\Filament\Resources\SubAnakAkuns\Pages\ListSubAnakAkuns;
use App\Filament\Resources\SubAnakAkuns\Schemas\SubAnakAkunForm;
use App\Filament\Resources\SubAnakAkuns\Tables\SubAnakAkunsTable;
use App\Models\SubAnakAkun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubAnakAkunResource extends Resource
{
    protected static ?string $model = SubAnakAkun::class;
    protected static ?string $label = 'Sub Anak Akun';
    protected static ?string $pluralLabel = 'Sub Anak Akun';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Sub Anak Akun';

    public static function form(Schema $schema): Schema
    {
        return SubAnakAkunForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubAnakAkunsTable::configure($table);
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
            'index' => ListSubAnakAkuns::route('/'),
            'create' => CreateSubAnakAkun::route('/create'),
            'edit' => EditSubAnakAkun::route('/{record}/edit'),
        ];
    }
}
