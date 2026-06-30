<?php

namespace App\Filament\Resources\TriplekHasilHps;

use App\Filament\Resources\TriplekHasilHps\Pages\CreateTriplekHasilHp;
use App\Filament\Resources\TriplekHasilHps\Pages\EditTriplekHasilHp;
use App\Filament\Resources\TriplekHasilHps\Pages\ListTriplekHasilHps;
use App\Filament\Resources\TriplekHasilHps\Schemas\TriplekHasilHpForm;
use App\Filament\Resources\TriplekHasilHps\Tables\TriplekHasilHpsTable;
use App\Models\TriplekHasilHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TriplekHasilHpResource extends Resource
{
    protected static ?string $model = TriplekHasilHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Triplek Hasil';

    public static function form(Schema $schema): Schema
    {
        return TriplekHasilHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TriplekHasilHpsTable::configure($table);
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
            'index' => ListTriplekHasilHps::route('/'),
            'create' => CreateTriplekHasilHp::route('/create'),
            'edit' => EditTriplekHasilHp::route('/{record}/edit'),
        ];
    }
}
