<?php

namespace App\Filament\Resources\PegawaiPotJeleks;

use App\Filament\Resources\PegawaiPotJeleks\Pages\CreatePegawaiPotJelek;
use App\Filament\Resources\PegawaiPotJeleks\Pages\EditPegawaiPotJelek;
use App\Filament\Resources\PegawaiPotJeleks\Pages\ListPegawaiPotJeleks;
use App\Filament\Resources\PegawaiPotJeleks\Schemas\PegawaiPotJelekForm;
use App\Filament\Resources\PegawaiPotJeleks\Tables\PegawaiPotJeleksTable;
use App\Models\PegawaiPotJelek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiPotJelekResource extends Resource
{
    protected static ?string $model = PegawaiPotJelek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiPotJelekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiPotJeleksTable::configure($table);
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
            'index' => ListPegawaiPotJeleks::route('/'),
            'create' => CreatePegawaiPotJelek::route('/create'),
            'edit' => EditPegawaiPotJelek::route('/{record}/edit'),
        ];
    }
}
