<?php

namespace App\Filament\Resources\ValidasiKedis;

use App\Filament\Resources\ValidasiKedis\Pages\CreateValidasiKedi;
use App\Filament\Resources\ValidasiKedis\Pages\EditValidasiKedi;
use App\Filament\Resources\ValidasiKedis\Pages\ListValidasiKedis;
use App\Filament\Resources\ValidasiKedis\Schemas\ValidasiKediForm;
use App\Filament\Resources\ValidasiKedis\Tables\ValidasiKedisTable;
use App\Models\ValidasiKedi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiKediResource extends Resource
{
    protected static ?string $model = ValidasiKedi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return ValidasiKediForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiKedisTable::configure($table);
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
            'index' => ListValidasiKedis::route('/'),
            'create' => CreateValidasiKedi::route('/create'),
            'edit' => EditValidasiKedi::route('/{record}/edit'),
        ];
    }
}
