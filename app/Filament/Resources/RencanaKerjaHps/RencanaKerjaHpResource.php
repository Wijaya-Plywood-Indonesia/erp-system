<?php

namespace App\Filament\Resources\RencanaKerjaHps;

use App\Filament\Resources\RencanaKerjaHps\Pages\CreateRencanaKerjaHp;
use App\Filament\Resources\RencanaKerjaHps\Pages\EditRencanaKerjaHp;
use App\Filament\Resources\RencanaKerjaHps\Pages\ListRencanaKerjaHps;
use App\Filament\Resources\RencanaKerjaHps\Schemas\RencanaKerjaHpForm;
use App\Filament\Resources\RencanaKerjaHps\Tables\RencanaKerjaHpsTable;
use App\Models\RencanaKerjaHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RencanaKerjaHpResource extends Resource
{
    protected static ?string $model = RencanaKerjaHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Hot Press';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'raker';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return RencanaKerjaHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RencanaKerjaHpsTable::configure($table);
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
            'index' => ListRencanaKerjaHps::route('/'),
            'create' => CreateRencanaKerjaHp::route('/create'),
            'edit' => EditRencanaKerjaHp::route('/{record}/edit'),
        ];
    }
}
