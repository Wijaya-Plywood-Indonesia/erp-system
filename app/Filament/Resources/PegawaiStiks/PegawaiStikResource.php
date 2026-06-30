<?php

namespace App\Filament\Resources\PegawaiStiks;

use App\Filament\Resources\PegawaiStiks\Pages\CreatePegawaiStik;
use App\Filament\Resources\PegawaiStiks\Pages\EditPegawaiStik;
use App\Filament\Resources\PegawaiStiks\Pages\ListPegawaiStiks;
use App\Filament\Resources\PegawaiStiks\Schemas\PegawaiStikForm;
use App\Filament\Resources\PegawaiStiks\Tables\PegawaiStiksTable;
use App\Models\DetailPegawaiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PegawaiStikResource extends Resource
{
    protected static ?string $model = DetailPegawaiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiStiksTable::configure($table);
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
            'index' => ListPegawaiStiks::route('/'),
            'create' => CreatePegawaiStik::route('/create'),
            'edit' => EditPegawaiStik::route('/{record}/edit'),
        ];
    }
}
