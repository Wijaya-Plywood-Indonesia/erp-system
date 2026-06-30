<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars;

use App\Filament\Resources\DetailNotaBarangKeluars\Pages\CreateDetailNotaBarangKeluar;
use App\Filament\Resources\DetailNotaBarangKeluars\Pages\EditDetailNotaBarangKeluar;
use App\Filament\Resources\DetailNotaBarangKeluars\Pages\ListDetailNotaBarangKeluars;
use App\Filament\Resources\DetailNotaBarangKeluars\Schemas\DetailNotaBarangKeluarForm;
use App\Filament\Resources\DetailNotaBarangKeluars\Tables\DetailNotaBarangKeluarsTable;
use App\Models\DetailNotaBarangKeluar;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailNotaBarangKeluarResource extends Resource
{
    protected static ?string $model = DetailNotaBarangKeluar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DetailNotaBarangKeluarForm::configure($schema);
    }
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DetailNotaBarangKeluarsTable::configure($table);
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
            'index' => ListDetailNotaBarangKeluars::route('/'),
            'create' => CreateDetailNotaBarangKeluar::route('/create'),
            'edit' => EditDetailNotaBarangKeluar::route('/{record}/edit'),
        ];
    }
}
