<?php

namespace App\Filament\Resources\Bks;

use App\Filament\Resources\Bks\Pages\CreateBk;
use App\Filament\Resources\Bks\Pages\EditBk;
use App\Filament\Resources\Bks\Pages\ListBks;
use App\Filament\Resources\Bks\Pages\ViewBk;
use App\Filament\Resources\Bks\RelationManagers\BkDetailRelationManager;
use App\Filament\Resources\Bks\Schemas\BkForm;
use App\Filament\Resources\Bks\Schemas\BkInfolist;
use App\Filament\Resources\Bks\Tables\BksTable;
use App\Models\Bk;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BkResource extends Resource
{
    protected static ?string $model = Bk::class;
    protected static ?string $slug = 'bk';
    protected static ?string $modelLabel = 'BK';
    protected static ?string $pluralModelLabel = 'BK';
    protected static ?string $navigationLabel = 'BK';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return config('bkbm.aktif') && parent::shouldRegisterNavigation();
    }

    public static function canAccess(): bool
    {
        return config('bkbm.aktif') && parent::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Bk::where('status', 'divalidasi')->count();
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'danger'; }

    public static function getNavigationBadgeTooltip(): ?string { return 'Barang masih di luar (belum kembali)'; }

    public static function form(Schema $schema): Schema { return BkForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return BkInfolist::configure($schema); }
    public static function table(Table $table): Table { return BksTable::configure($table); }

    public static function getRelations(): array { return [BkDetailRelationManager::class]; }

    public static function getPages(): array
    {
        return [
            'index' => ListBks::route('/'),
            'create' => CreateBk::route('/create'),
            'view' => ViewBk::route('/{record}'),
            'edit' => EditBk::route('/{record}/edit'),
        ];
    }
}
