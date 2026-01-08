<?php

namespace App\Filament\Resources\ListPekerjaanMenumpuks;

use App\Filament\Resources\ListPekerjaanMenumpuks\Pages\CreateListPekerjaanMenumpuk;
use App\Filament\Resources\ListPekerjaanMenumpuks\Pages\EditListPekerjaanMenumpuk;
use App\Filament\Resources\ListPekerjaanMenumpuks\Pages\ListListPekerjaanMenumpuks;
use App\Filament\Resources\ListPekerjaanMenumpuks\Schemas\ListPekerjaanMenumpukForm;
use App\Filament\Resources\ListPekerjaanMenumpuks\Tables\ListPekerjaanMenumpuksTable;
use App\Models\ListPekerjaanMenumpuk;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListPekerjaanMenumpukResource extends Resource
{
    protected static ?string $model = ListPekerjaanMenumpuk::class;

    // Menggunakan Heroicon class sesuai standar Anda
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Finishing';
    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    /**
     * Eager Loading untuk efisiensi dan memastikan tanggal terbaca
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'hasilPilihPlywood.produksiPilihPlywood', // Mengambil tanggal_produksi
                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                'hasilPilihPlywood.barangSetengahJadiHp.grade',
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return ListPekerjaanMenumpukForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ListPekerjaanMenumpuksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListPekerjaanMenumpuks::route('/'),
            'create' => CreateListPekerjaanMenumpuk::route('/create'),
            'edit' => EditListPekerjaanMenumpuk::route('/{record}/edit'),
        ];
    }
}