<?php

namespace App\Filament\Resources\KontrakKerjas;

use App\Filament\Resources\KontrakKerjas\Pages\CreateKontrakKerja;
use App\Filament\Resources\KontrakKerjas\Pages\EditKontrakKerja;
use App\Filament\Resources\KontrakKerjas\Pages\ListKontrakKerjas;
use App\Filament\Resources\KontrakKerjas\Pages\ViewKontrakKerja;
use App\Filament\Resources\KontrakKerjas\Schemas\KontrakKerjaForm;
use App\Filament\Resources\KontrakKerjas\Schemas\KontrakKerjaInfolist;
use App\Filament\Resources\KontrakKerjas\Tables\KontrakKerjasTable;
use App\Models\KontrakKerja;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class KontrakKerjaResource extends Resource
{
    protected static ?string $model = KontrakKerja::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static string|UnitEnum|null $navigationGroup = 'Pegawai & Kontrak';
    protected static ?string $recordTitleAttribute = 'no_kontrak';
    protected static ?string $navigationLabel = 'Kontrak Kerja';
    protected static ?string $pluralModelLabel = 'Kontrak Kerja';
    protected static ?string $modelLabel = 'Kontrak Kerja';

    public static function form(Schema $schema): Schema
    {
        return KontrakKerjaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KontrakKerjaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KontrakKerjasTable::configure($table);
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
            'index' => ListKontrakKerjas::route('/'),
            'create' => CreateKontrakKerja::route('/create'),
            'view' => ViewKontrakKerja::route('/{record}'),
            'edit' => EditKontrakKerja::route('/{record}/edit'),
        ];
    }
    public static function getNavigationBadge(): ?string
    {
        $expired = KontrakKerja::where('status_kontrak', 'expired')->count();
        $soon = KontrakKerja::where('status_kontrak', 'soon')->count();

        $total = $expired + $soon;

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger'; // warna merah
    }
}
