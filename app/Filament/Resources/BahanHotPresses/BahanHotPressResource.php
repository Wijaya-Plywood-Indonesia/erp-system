<?php

namespace App\Filament\Resources\BahanHotPresses;

use App\Filament\Resources\BahanHotPresses\Pages\CreateBahanHotPress;
use App\Filament\Resources\BahanHotPresses\Pages\EditBahanHotPress;
use App\Filament\Resources\BahanHotPresses\Pages\ListBahanHotPresses;
use App\Filament\Resources\BahanHotPresses\Schemas\BahanHotPressForm;
use App\Filament\Resources\BahanHotPresses\Tables\BahanHotPressesTable;
use App\Models\BahanHotpress;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanHotPressResource extends Resource
{
    protected static ?string $model = BahanHotpress::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return BahanHotPressForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanHotPressesTable::configure($table);
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
            'index' => ListBahanHotPresses::route('/'),
            'create' => CreateBahanHotPress::route('/create'),
            'edit' => EditBahanHotPress::route('/{record}/edit'),
        ];
    }
}
