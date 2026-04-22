<?php

namespace App\Filament\Resources\OpnameStokKayus;

use App\Filament\Resources\OpnameStokKayus\Pages\CreateOpnameStokKayu;
use App\Filament\Resources\OpnameStokKayus\Pages\EditOpnameStokKayu;
use App\Filament\Resources\OpnameStokKayus\Pages\ListOpnameStokKayus;
use App\Filament\Resources\OpnameStokKayus\Schemas\OpnameStokKayuForm;
use App\Filament\Resources\OpnameStokKayus\Tables\OpnameStokKayusTable;
use App\Models\OpnameStokKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OpnameStokKayuResource extends Resource
{
    protected static ?string $model = OpnameStokKayu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return OpnameStokKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OpnameStokKayusTable::configure($table);
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
            'index' => ListOpnameStokKayus::route('/'),
            'create' => CreateOpnameStokKayu::route('/create'),
            'edit' => EditOpnameStokKayu::route('/{record}/edit'),
        ];
    }
}
