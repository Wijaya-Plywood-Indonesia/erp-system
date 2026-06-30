<?php

namespace App\Filament\Resources\DetailMasukKedis;

use App\Filament\Resources\DetailMasukKedis\Pages\CreateDetailMasukKedi;
use App\Filament\Resources\DetailMasukKedis\Pages\EditDetailMasukKedi;
use App\Filament\Resources\DetailMasukKedis\Pages\ListDetailMasukKedis;
use App\Filament\Resources\DetailMasukKedis\Schemas\DetailMasukKediForm;
use App\Filament\Resources\DetailMasukKedis\Tables\DetailMasukKedisTable;
use App\Models\DetailMasukKedi;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailMasukKediResource extends Resource
{
    protected static ?string $model = DetailMasukKedi::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailMasukKediForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return DetailMasukKedisTable::configure($table);
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
            'index' => ListDetailMasukKedis::route('/'),
            'create' => CreateDetailMasukKedi::route('/create'),
            'edit' => EditDetailMasukKedi::route('/{record}/edit'),
        ];
    }
}