<?php

namespace App\Filament\Resources\BahanDempuls;

use App\Filament\Resources\BahanDempuls\Pages\CreateBahanDempul;
use App\Filament\Resources\BahanDempuls\Pages\EditBahanDempul;
use App\Filament\Resources\BahanDempuls\Pages\ListBahanDempuls;
use App\Filament\Resources\BahanDempuls\Schemas\BahanDempulForm;
use App\Filament\Resources\BahanDempuls\Tables\BahanDempulsTable;
use App\Models\BahanDempul;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanDempulResource extends Resource
{
    protected static ?string $model = BahanDempul::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return BahanDempulForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanDempulsTable::configure($table);
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
            'index' => ListBahanDempuls::route('/'),
            'create' => CreateBahanDempul::route('/create'),
            'edit' => EditBahanDempul::route('/{record}/edit'),
        ];
    }
}
