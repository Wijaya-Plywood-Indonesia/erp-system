<?php

namespace App\Filament\Resources\PlatformBahanHps;

use App\Filament\Resources\PlatformBahanHps\Pages\CreatePlatformBahanHp;
use App\Filament\Resources\PlatformBahanHps\Pages\EditPlatformBahanHp;
use App\Filament\Resources\PlatformBahanHps\Pages\ListPlatformBahanHps;
use App\Filament\Resources\PlatformBahanHps\Schemas\PlatformBahanHpForm;
use App\Filament\Resources\PlatformBahanHps\Tables\PlatformBahanHpsTable;
use App\Models\PlatformBahanHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlatformBahanHpResource extends Resource
{
    protected static ?string $model = PlatformBahanHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Platform Bahan';

    public static function form(Schema $schema): Schema
    {
        return PlatformBahanHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformBahanHpsTable::configure($table);
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
            'index' => ListPlatformBahanHps::route('/'),
            'create' => CreatePlatformBahanHp::route('/create'),
            'edit' => EditPlatformBahanHp::route('/{record}/edit'),
        ];
    }
}
