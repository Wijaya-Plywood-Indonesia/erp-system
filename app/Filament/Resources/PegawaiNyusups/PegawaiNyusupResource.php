<?php

namespace App\Filament\Resources\PegawaiNyusups;

use App\Filament\Resources\PegawaiNyusups\Pages\CreatePegawaiNyusup;
use App\Filament\Resources\PegawaiNyusups\Pages\EditPegawaiNyusup;
use App\Filament\Resources\PegawaiNyusups\Pages\ListPegawaiNyusups;
use App\Filament\Resources\PegawaiNyusups\Schemas\PegawaiNyusupForm;
use App\Filament\Resources\PegawaiNyusups\Tables\PegawaiNyusupsTable;
use App\Models\PegawaiNyusup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiNyusupResource extends Resource
{
    protected static ?string $model = PegawaiNyusup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiNyusupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiNyusupsTable::configure($table);
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
            'index' => ListPegawaiNyusups::route('/'),
            'create' => CreatePegawaiNyusup::route('/create'),
            'edit' => EditPegawaiNyusup::route('/{record}/edit'),
        ];
    }
}
