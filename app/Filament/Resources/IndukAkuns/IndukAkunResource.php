<?php

namespace App\Filament\Resources\IndukAkuns;

use App\Filament\Resources\IndukAkuns\Pages\CreateIndukAkun;
use App\Filament\Resources\IndukAkuns\Pages\EditIndukAkun;
use App\Filament\Resources\IndukAkuns\Pages\ListIndukAkuns;
use App\Filament\Resources\IndukAkuns\Pages\ViewIndukAkun;
use App\Filament\Resources\IndukAkuns\RelationManagers\AnakAkunsRelationManager;
use App\Filament\Resources\IndukAkuns\RelationManagers\SubAnakAkunsRelationManager;
use App\Filament\Resources\IndukAkuns\Schemas\IndukAkunForm;
use App\Filament\Resources\IndukAkuns\Schemas\IndukAkunInfolist;
use App\Filament\Resources\IndukAkuns\Tables\IndukAkunsTable;
use App\Models\IndukAkun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class IndukAkunResource extends Resource
{
    protected static ?string $model = IndukAkun::class;
    protected static ?string $label = 'Master Akun';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocument;
    protected static string|UnitEnum|null $navigationGroup = 'Jurnal';
    protected static ?string $navigationLabel = 'Master Akun';
    protected static ?string $pluralModelLabel = 'Master Akun';
    protected static ?string $modelLabel = 'Master Akun';


    public static function form(Schema $schema): Schema
    {
        return IndukAkunForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return IndukAkunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IndukAkunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AnakAkunsRelationManager::class,
            SubAnakAkunsRelationManager::class,
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIndukAkuns::route('/'),
            'create' => CreateIndukAkun::route('/create'),
            'view' => ViewIndukAkun::route('/{record}'),
            'edit' => EditIndukAkun::route('/{record}/edit'),
        ];
    }
}
