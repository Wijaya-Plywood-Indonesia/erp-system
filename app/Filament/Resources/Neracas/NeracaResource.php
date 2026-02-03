<?php

namespace App\Filament\Resources\Neracas;

use App\Filament\Resources\Neracas\Pages\CreateNeraca;
use App\Filament\Resources\Neracas\Pages\EditNeraca;
use App\Filament\Resources\Neracas\Pages\ListNeracas;
use App\Filament\Resources\Neracas\Schemas\NeracaForm;
use App\Filament\Resources\Neracas\Tables\NeracasTable;
use App\Models\Neraca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NeracaResource extends Resource
{
    protected static ?string $model = Neraca::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Neraca';

    public static function form(Schema $schema): Schema
    {
        return NeracaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NeracasTable::configure($table);
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
            'index' => ListNeracas::route('/'),
            'create' => CreateNeraca::route('/create'),
            'edit' => EditNeraca::route('/{record}/edit'),
        ];
    }
}
