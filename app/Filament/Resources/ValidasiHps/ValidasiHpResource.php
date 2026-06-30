<?php

namespace App\Filament\Resources\ValidasiHps;

use App\Filament\Resources\ValidasiHps\Pages\CreateValidasiHp;
use App\Filament\Resources\ValidasiHps\Pages\EditValidasiHp;
use App\Filament\Resources\ValidasiHps\Pages\ListValidasiHps;
use App\Filament\Resources\ValidasiHps\Schemas\ValidasiHpForm;
use App\Filament\Resources\ValidasiHps\Tables\ValidasiHpsTable;
use App\Models\ValidasiHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiHpResource extends Resource
{
    protected static ?string $model = ValidasiHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Validasi Hp';

    public static function form(Schema $schema): Schema
    {
        return ValidasiHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiHpsTable::configure($table);
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
            'index' => ListValidasiHps::route('/'),
            'create' => CreateValidasiHp::route('/create'),
            'edit' => EditValidasiHp::route('/{record}/edit'),
        ];
    }
}
