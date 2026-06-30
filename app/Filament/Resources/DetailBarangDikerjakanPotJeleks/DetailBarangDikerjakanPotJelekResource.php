<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotJeleks;

use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Pages\CreateDetailBarangDikerjakanPotJelek;
use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Pages\EditDetailBarangDikerjakanPotJelek;
use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Pages\ListDetailBarangDikerjakanPotJeleks;
use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Schemas\DetailBarangDikerjakanPotJelekForm;
use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Tables\DetailBarangDikerjakanPotJeleksTable;
use App\Models\DetailBarangDikerjakanPotJelek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailBarangDikerjakanPotJelekResource extends Resource
{
    protected static ?string $model = DetailBarangDikerjakanPotJelek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanPotJelekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailBarangDikerjakanPotJeleksTable::configure($table);
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
            'index' => ListDetailBarangDikerjakanPotJeleks::route('/'),
            'create' => CreateDetailBarangDikerjakanPotJelek::route('/create'),
            'edit' => EditDetailBarangDikerjakanPotJelek::route('/{record}/edit'),
        ];
    }
}
