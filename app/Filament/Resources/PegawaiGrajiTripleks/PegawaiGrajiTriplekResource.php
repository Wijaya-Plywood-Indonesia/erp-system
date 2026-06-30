<?php

namespace App\Filament\Resources\PegawaiGrajiTripleks;

use App\Filament\Resources\PegawaiGrajiTripleks\Pages\CreatePegawaiGrajiTriplek;
use App\Filament\Resources\PegawaiGrajiTripleks\Pages\EditPegawaiGrajiTriplek;
use App\Filament\Resources\PegawaiGrajiTripleks\Pages\ListPegawaiGrajiTripleks;
use App\Filament\Resources\PegawaiGrajiTripleks\Schemas\PegawaiGrajiTriplekForm;
use App\Filament\Resources\PegawaiGrajiTripleks\Tables\PegawaiGrajiTripleksTable;
use App\Models\PegawaiGrajiTriplek;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiGrajiTriplekResource extends Resource
{
    protected static ?string $model = PegawaiGrajiTriplek::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Pegawai';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiGrajiTriplekForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiGrajiTripleksTable::configure($table);
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
            'index' => ListPegawaiGrajiTripleks::route('/'),
            'create' => CreatePegawaiGrajiTriplek::route('/create'),
            'edit' => EditPegawaiGrajiTriplek::route('/{record}/edit'),
        ];
    }
}
