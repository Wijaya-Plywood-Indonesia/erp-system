<?php

namespace App\Filament\Resources\BahanPenolongHps;

use App\Filament\Resources\BahanPenolongHps\Pages\CreateBahanPenolongHp;
use App\Filament\Resources\BahanPenolongHps\Pages\EditBahanPenolongHp;
use App\Filament\Resources\BahanPenolongHps\Pages\ListBahanPenolongHps;
use App\Filament\Resources\BahanPenolongHps\Schemas\BahanPenolongHpForm;
use App\Filament\Resources\BahanPenolongHps\Tables\BahanPenolongHpsTable;
use App\Models\BahanPenolongHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanPenolongHpResource extends Resource
{
    protected static ?string $model = BahanPenolongHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $recordTitleAttribute = 'Bahan Penolong';

    public static function form(Schema $schema): Schema
    {
        return BahanPenolongHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanPenolongHpsTable::configure($table);
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
            'index' => ListBahanPenolongHps::route('/'),
            'create' => CreateBahanPenolongHp::route('/create'),
            'edit' => EditBahanPenolongHp::route('/{record}/edit'),
        ];
    }
}
