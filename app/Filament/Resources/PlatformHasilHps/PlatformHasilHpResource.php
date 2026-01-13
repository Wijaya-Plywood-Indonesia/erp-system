<?php

namespace App\Filament\Resources\PlatformHasilHps;

use App\Filament\Resources\PlatformHasilHps\Pages\CreatePlatformHasilHp;
use App\Filament\Resources\PlatformHasilHps\Pages\EditPlatformHasilHp;
use App\Filament\Resources\PlatformHasilHps\Pages\ListPlatformHasilHps;
use App\Filament\Resources\PlatformHasilHps\Schemas\PlatformHasilHpForm;
use App\Filament\Resources\PlatformHasilHps\Tables\PlatformHasilHpsTable;
use App\Models\PlatformHasilHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlatformHasilHpResource extends Resource
{
    protected static ?string $model = PlatformHasilHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Platform Hasil';

    public static function form(Schema $schema): Schema
    {
        return PlatformHasilHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformHasilHpsTable::configure($table);
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
            'index' => ListPlatformHasilHps::route('/'),
            'create' => CreatePlatformHasilHp::route('/create'),
            'edit' => EditPlatformHasilHp::route('/{record}/edit'),
        ];
    }
}
