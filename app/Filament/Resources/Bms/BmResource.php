<?php

namespace App\Filament\Resources\Bms;

use App\Filament\Resources\Bms\Pages\CreateBm;
use App\Filament\Resources\Bms\Pages\EditBm;
use App\Filament\Resources\Bms\Pages\ListBms;
use App\Filament\Resources\Bms\Pages\ViewBm;
use App\Filament\Resources\Bms\RelationManagers\BmDetailRelationManager;
use App\Filament\Resources\Bms\Schemas\BmForm;
use App\Filament\Resources\Bms\Schemas\BmInfolist;
use App\Filament\Resources\Bms\Tables\BmsTable;
use App\Models\Bm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BmResource extends Resource
{
    protected static ?string $model = Bm::class;
    protected static ?string $slug = 'bm';
    protected static ?string $modelLabel = 'BM';
    protected static ?string $pluralModelLabel = 'BM';
    protected static ?string $navigationLabel = 'BM';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;
    protected static string|UnitEnum|null $navigationGroup = 'BK-BM';
    protected static ?int $navigationSort = 2;

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
        // BM dari pabrik lain yang belum diisi/divalidasi.
        $n = Bm::where('status', 'draft')->whereNotNull('ref_no_bk')->count();
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    public static function getNavigationBadgeTooltip(): ?string { return 'BM dari pabrik lain yang menunggu diisi'; }

    public static function form(Schema $schema): Schema { return BmForm::configure($schema); }
    public static function infolist(Schema $schema): Schema { return BmInfolist::configure($schema); }
    public static function table(Table $table): Table { return BmsTable::configure($table); }

    public static function getRelations(): array { return [BmDetailRelationManager::class]; }

    public static function getPages(): array
    {
        return [
            'index' => ListBms::route('/'),
            'create' => CreateBm::route('/create'),
            'view' => ViewBm::route('/{record}'),
            'edit' => EditBm::route('/{record}/edit'),
        ];
    }
}
