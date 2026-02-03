<?php

namespace App\Filament\Resources\JurnalTigas;

use App\Filament\Resources\JurnalTigas\Pages\CreateJurnalTiga;
use App\Filament\Resources\JurnalTigas\Pages\EditJurnalTiga;
use App\Filament\Resources\JurnalTigas\Pages\ListJurnalTigas;
use App\Filament\Resources\JurnalTigas\Schemas\JurnalTigaForm;
use App\Filament\Resources\JurnalTigas\Tables\JurnalTigasTable;
use App\Models\JurnalTiga;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class JurnalTigaResource extends Resource
{
    protected static ?string $model = JurnalTiga::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'JurnalTiga';

    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';

    // Menentukan label untuk satu rekaman (misal: "Create Produksi3th")
    protected static ?string $modelLabel = 'Jurnal 3th';

    protected static ?int $navigationSort = 3;

    // Menentukan label untuk jamak/daftar (misal: Judul di tabel List)
    protected static ?string $pluralModelLabel = 'Jurnal 3th';

    public static function form(Schema $schema): Schema
    {
        return JurnalTigaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return JurnalTigasTable::configure($table);
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
            'index' => ListJurnalTigas::route('/'),
            'create' => CreateJurnalTiga::route('/create'),
            'edit' => EditJurnalTiga::route('/{record}/edit'),
        ];
    }
}
