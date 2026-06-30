<?php

namespace App\Filament\Resources\DetailDempuls;

use App\Filament\Resources\DetailDempuls\Pages\CreateDetailDempul;
use App\Filament\Resources\DetailDempuls\Pages\EditDetailDempul;
use App\Filament\Resources\DetailDempuls\Pages\ListDetailDempuls;
use App\Filament\Resources\DetailDempuls\Schemas\DetailDempulForm;
use App\Filament\Resources\DetailDempuls\Tables\DetailDempulsTable;
use App\Models\DetailDempul;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailDempulResource extends Resource
{
    protected static ?string $model = DetailDempul::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailDempulForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailDempulsTable::configure($table);
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
            'index' => ListDetailDempuls::route('/'),
            'create' => CreateDetailDempul::route('/create'),
            'edit' => EditDetailDempul::route('/{record}/edit'),
        ];
    }
}
