<?php

namespace App\Filament\Resources\PegawaiTurunKayus;

use App\Filament\Resources\PegawaiTurunKayus\Pages\CreatePegawaiTurunKayu;
use App\Filament\Resources\PegawaiTurunKayus\Pages\EditPegawaiTurunKayu;
use App\Filament\Resources\PegawaiTurunKayus\Pages\ListPegawaiTurunKayus;
use App\Filament\Resources\PegawaiTurunKayus\Schemas\PegawaiTurunKayuForm;
use App\Filament\Resources\PegawaiTurunKayus\Tables\PegawaiTurunKayusTable;
use App\Models\PegawaiTurunKayu;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiTurunKayuResource extends Resource
{
    protected static ?string $model = PegawaiTurunKayu::class;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PegawaiTurunKayuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiTurunKayusTable::configure($table);
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
            'index' => ListPegawaiTurunKayus::route('/'),
            'create' => CreatePegawaiTurunKayu::route('/create'),
            'edit' => EditPegawaiTurunKayu::route('/{record}/edit'),
        ];
    }
}
