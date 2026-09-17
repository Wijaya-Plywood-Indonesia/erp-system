<?php

namespace App\Filament\Resources\ProduksiStiks;

use App\Filament\Resources\ProduksiStiks\Pages\CreateProduksiStik;
use App\Filament\Resources\ProduksiStiks\Pages\EditProduksiStik;
use App\Filament\Resources\ProduksiStiks\Pages\ListProduksiStiks;
use App\Filament\Resources\ProduksiStiks\Pages\ViewProduksiStik;
use App\Filament\Resources\ProduksiStiks\Schemas\ProduksiStikForm;
use App\Filament\Resources\ProduksiStiks\Tables\ProduksiStiksTable;
use App\Filament\Resources\ProduksiStiks\Schemas\ProduksiStikInfoList;
use App\Models\ProduksiStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
use App\Services\ProduksiLockService;
use Illuminate\Database\Eloquent\Model;

class ProduksiStikResource extends Resource
{
    protected static ?string $model = ProduksiStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Dryer';
    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'Stik';

    /**
     * Guard server-side: walau URL /edit diakses langsung, produksi yang
     * sudah divalidasi tetap tidak bisa diedit — kecuali Super Admin.
     */
    public static function canEdit(Model $record): bool
    {
        if (ProduksiLockService::isLocked($record, 'validasiStik')) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        if (ProduksiLockService::isLocked($record, 'validasiStik')) {
            return false;
        }

        return parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return ProduksiStikForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiStikInfoList::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiStiksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Serah terima dari Rotary DIHAPUS — Produksi Stik sekarang
            // sepenuhnya manual (modal diisi sendiri, bukan dari palet Rotary).
            RelationManagers\DetailPegawaiStikRelationManager::class,
            RelationManagers\DetailMasukStikRelationManager::class,
            RelationManagers\DetailHasilStikRelationManager::class,
            RelationManagers\ValidasiStikRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiStiks::route('/'),
            'create' => CreateProduksiStik::route('/create'),
            'view' => ViewProduksiStik::route('/{record}'),
            'edit' => EditProduksiStik::route('/{record}/edit'),
        ];
    }
}