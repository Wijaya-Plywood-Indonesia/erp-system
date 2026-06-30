<?php

namespace App\Filament\Resources\KayuPecahRotaries;

use App\Filament\Resources\KayuPecahRotaries\Pages\CreateKayuPecahRotary;
use App\Filament\Resources\KayuPecahRotaries\Pages\EditKayuPecahRotary;
use App\Filament\Resources\KayuPecahRotaries\Pages\ListKayuPecahRotaries;
use App\Filament\Resources\KayuPecahRotaries\Schemas\KayuPecahRotaryForm;
use App\Filament\Resources\KayuPecahRotaries\Tables\KayuPecahRotariesTable;
use App\Models\KayuPecahRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KayuPecahRotaryResource extends Resource
{
    protected static ?string $model = KayuPecahRotary::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return KayuPecahRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KayuPecahRotariesTable::configure($table);
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
            'index' => ListKayuPecahRotaries::route('/'),
            'create' => CreateKayuPecahRotary::route('/create'),
            'edit' => EditKayuPecahRotary::route('/{record}/edit'),
        ];
    }
}
