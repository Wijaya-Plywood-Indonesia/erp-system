<?php

namespace App\Filament\Resources\DetailHasils;

use App\Filament\Resources\DetailHasils\Pages\CreateDetailHasil;
use App\Filament\Resources\DetailHasils\Pages\EditDetailHasil;
use App\Filament\Resources\DetailHasils\Pages\ListDetailHasils;
use App\Filament\Resources\DetailHasils\Schemas\DetailHasilForm;
use App\Filament\Resources\DetailHasils\Tables\DetailHasilsTable;
use App\Models\DetailHasil;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DetailHasilResource extends Resource
{
    protected static ?string $model = DetailHasil::class;
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    public static function form(Schema $schema): Schema
    {
        return DetailHasilForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailHasilsTable::configure($table);
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
            'index' => ListDetailHasils::route('/'),
            'create' => CreateDetailHasil::route('/create'),
            'edit' => EditDetailHasil::route('/{record}/edit'),
        ];
    }
}
