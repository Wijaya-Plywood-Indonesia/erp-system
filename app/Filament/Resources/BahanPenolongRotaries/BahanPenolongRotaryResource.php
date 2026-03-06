<?php

namespace App\Filament\Resources\BahanPenolongRotaries;

use App\Filament\Resources\BahanPenolongRotaries\Pages\CreateBahanPenolongRotary;
use App\Filament\Resources\BahanPenolongRotaries\Pages\EditBahanPenolongRotary;
use App\Filament\Resources\BahanPenolongRotaries\Pages\ListBahanPenolongRotaries;
use App\Filament\Resources\BahanPenolongRotaries\Schemas\BahanPenolongRotaryForm;
use App\Filament\Resources\BahanPenolongRotaries\Tables\BahanPenolongRotariesTable;
use App\Models\BahanPenolongRotary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BahanPenolongRotaryResource extends Resource
{
    protected static ?string $model = BahanPenolongRotary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return BahanPenolongRotaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BahanPenolongRotariesTable::configure($table);
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
            'index' => ListBahanPenolongRotaries::route('/'),
            'create' => CreateBahanPenolongRotary::route('/create'),
            'edit' => EditBahanPenolongRotary::route('/{record}/edit'),
        ];
    }
}
