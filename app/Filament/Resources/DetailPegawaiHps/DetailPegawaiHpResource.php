<?php

namespace App\Filament\Resources\DetailPegawaiHps;

use App\Filament\Resources\DetailPegawaiHps\Pages\CreateDetailPegawaiHp;
use App\Filament\Resources\DetailPegawaiHps\Pages\EditDetailPegawaiHp;
use App\Filament\Resources\DetailPegawaiHps\Pages\ListDetailPegawaiHps;
use App\Filament\Resources\DetailPegawaiHps\Schemas\DetailPegawaiHpForm;
use App\Filament\Resources\DetailPegawaiHps\Tables\DetailPegawaiHpsTable;
use App\Models\DetailPegawaiHp;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailPegawaiHpResource extends Resource
{
    protected static ?string $model = DetailPegawaiHp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return DetailPegawaiHpForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailPegawaiHpsTable::configure($table);
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
            'index' => ListDetailPegawaiHps::route('/'),
            'create' => CreateDetailPegawaiHp::route('/create'),
            'edit' => EditDetailPegawaiHp::route('/{record}/edit'),
        ];
    }
}
