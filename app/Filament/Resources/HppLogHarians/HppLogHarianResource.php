<?php

namespace App\Filament\Resources\HppLogHarians;

use App\Filament\Resources\HppLogHarians\Pages\CreateHppLogHarian;
use App\Filament\Resources\HppLogHarians\Pages\EditHppLogHarian;
use App\Filament\Resources\HppLogHarians\Pages\ListHppLogHarians;
use App\Filament\Resources\HppLogHarians\Schemas\HppLogHarianForm;
use App\Filament\Resources\HppLogHarians\Tables\HppLogHariansTable;
use App\Models\HppLogHarian;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HppLogHarianResource extends Resource
{
    protected static ?string $model = HppLogHarian::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'HPP & Biaya';
    protected static ?string $navigationLabel = 'Log HPP Harian';
    protected static ?string $modelLabel = 'Log HPP Harian';
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return HppLogHarianForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HppLogHariansTable::configure($table);
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
            'index' => ListHppLogHarians::route('/'),
            'create' => CreateHppLogHarian::route('/create'),
            'edit' => EditHppLogHarian::route('/{record}/edit'),
        ];
    }
}
