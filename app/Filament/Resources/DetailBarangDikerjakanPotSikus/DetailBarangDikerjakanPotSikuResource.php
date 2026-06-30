<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotSikus;

use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Pages\CreateDetailBarangDikerjakanPotSiku;
use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Pages\EditDetailBarangDikerjakanPotSiku;
use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Pages\ListDetailBarangDikerjakanPotSikus;
use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Schemas\DetailBarangDikerjakanPotSikuForm;
use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Tables\DetailBarangDikerjakanPotSikusTable;
use App\Models\DetailBarangDikerjakanPotSiku;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailBarangDikerjakanPotSikuResource extends Resource
{
    protected static ?string $model = DetailBarangDikerjakanPotSiku::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanPotSikuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailBarangDikerjakanPotSikusTable::configure($table);
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
            'index' => ListDetailBarangDikerjakanPotSikus::route('/'),
            'create' => CreateDetailBarangDikerjakanPotSiku::route('/create'),
            'edit' => EditDetailBarangDikerjakanPotSiku::route('/{record}/edit'),
        ];
    }
}
