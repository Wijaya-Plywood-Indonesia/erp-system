<?php

namespace App\Filament\Resources\PegawaiGuellotines;

use App\Filament\Resources\PegawaiGuellotines\Pages\CreatePegawaiGuellotine;
use App\Filament\Resources\PegawaiGuellotines\Pages\EditPegawaiGuellotine;
use App\Filament\Resources\PegawaiGuellotines\Pages\ListPegawaiGuellotines;
use App\Filament\Resources\PegawaiGuellotines\Schemas\PegawaiGuellotineForm;
use App\Filament\Resources\PegawaiGuellotines\Tables\PegawaiGuellotinesTable;
use App\Models\pegawai_guellotine;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiGuellotineResource extends Resource
{
    protected static ?string $model = pegawai_guellotine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiGuellotineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiGuellotinesTable::configure($table);
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
            'index' => ListPegawaiGuellotines::route('/'),
            'create' => CreatePegawaiGuellotine::route('/create'),
            'edit' => EditPegawaiGuellotine::route('/{record}/edit'),
        ];
    }
}
