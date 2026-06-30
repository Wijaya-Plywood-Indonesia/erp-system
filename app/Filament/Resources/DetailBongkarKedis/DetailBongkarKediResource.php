<?php

namespace App\Filament\Resources\DetailBongkarKedis;

use App\Filament\Resources\DetailBongkarKedis\Pages\CreateDetailBongkarKedi;
use App\Filament\Resources\DetailBongkarKedis\Pages\EditDetailBongkarKedi;
use App\Filament\Resources\DetailBongkarKedis\Pages\ListDetailBongkarKedis;
use App\Filament\Resources\DetailBongkarKedis\Schemas\DetailBongkarKediForm;
use App\Filament\Resources\DetailBongkarKedis\Tables\DetailBongkarKedisTable;
use App\Models\DetailBongkarKedi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailBongkarKediResource extends Resource
{
    protected static ?string $model = DetailBongkarKedi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailBongkarKediForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailBongkarKedisTable::configure($table);
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
            'index' => ListDetailBongkarKedis::route('/'),
            'create' => CreateDetailBongkarKedi::route('/create'),
            'edit' => EditDetailBongkarKedi::route('/{record}/edit'),
        ];
    }

}