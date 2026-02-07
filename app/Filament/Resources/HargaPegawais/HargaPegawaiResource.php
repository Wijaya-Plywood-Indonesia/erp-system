<?php

namespace App\Filament\Resources\HargaPegawais;

use App\Filament\Resources\HargaPegawais\Pages\CreateHargaPegawai;
use App\Filament\Resources\HargaPegawais\Pages\EditHargaPegawai;
use App\Filament\Resources\HargaPegawais\Pages\ListHargaPegawais;
use App\Filament\Resources\HargaPegawais\Schemas\HargaPegawaiForm;
use App\Filament\Resources\HargaPegawais\Tables\HargaPegawaisTable;
use App\Models\HargaPegawai;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HargaPegawaiResource extends Resource
{
    protected static ?string $model = HargaPegawai::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Master';

    protected static ?string $recordTitleAttribute = 'HargaPegawai';

    public static function form(Schema $schema): Schema
    {
        return HargaPegawaiForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HargaPegawaisTable::configure($table);
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
            'index' => ListHargaPegawais::route('/'),
            'create' => CreateHargaPegawai::route('/create'),
            'edit' => EditHargaPegawai::route('/{record}/edit'),
        ];
    }
}
