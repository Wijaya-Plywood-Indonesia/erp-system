<?php

namespace App\Filament\Resources\JurnalUmums;

use App\Filament\Resources\JurnalUmums\Pages\CreateJurnalUmum;
use App\Filament\Resources\JurnalUmums\Pages\EditJurnalUmum;
use App\Filament\Resources\JurnalUmums\Pages\ListJurnalUmums;
use App\Filament\Resources\JurnalUmums\Schemas\JurnalUmumForm;
use App\Filament\Resources\JurnalUmums\Tables\JurnalUmumsTable;
use App\Models\JurnalUmum;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class JurnalUmumResource extends Resource
{
    protected static ?string $model = JurnalUmum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';


    public static function form(Schema $schema): Schema
    {
        return JurnalUmumForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JurnalUmumsTable::configure($table);
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
            'index' => ListJurnalUmums::route('/'),
            'create' => CreateJurnalUmum::route('/create'),
            'edit' => EditJurnalUmum::route('/{record}/edit'),
        ];
    }
}
