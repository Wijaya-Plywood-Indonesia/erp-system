<?php

namespace App\Filament\Resources\DetailBarangDikerjakans;

use App\Filament\Resources\DetailBarangDikerjakans\Pages\CreateDetailBarangDikerjakan;
use App\Filament\Resources\DetailBarangDikerjakans\Pages\EditDetailBarangDikerjakan;
use App\Filament\Resources\DetailBarangDikerjakans\Pages\ListDetailBarangDikerjakans;
use App\Filament\Resources\DetailBarangDikerjakans\Schemas\DetailBarangDikerjakanForm;
use App\Filament\Resources\DetailBarangDikerjakans\Tables\DetailBarangDikerjakansTable;
use App\Models\DetailBarangDikerjakan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailBarangDikerjakanResource extends Resource
{
    protected static ?string $model = DetailBarangDikerjakan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailBarangDikerjakansTable::configure($table);
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
            'index' => ListDetailBarangDikerjakans::route('/'),
            'create' => CreateDetailBarangDikerjakan::route('/create'),
            'edit' => EditDetailBarangDikerjakan::route('/{record}/edit'),
        ];
    }
}
