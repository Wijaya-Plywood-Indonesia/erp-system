<?php

namespace App\Filament\Resources\DetailMasukStiks;

use App\Filament\Resources\DetailMasukStiks\Pages\CreateDetailMasukStik;
use App\Filament\Resources\DetailMasukStiks\Pages\EditDetailMasukStik;
use App\Filament\Resources\DetailMasukStiks\Pages\ListDetailMasukStiks;
use App\Filament\Resources\DetailMasukStiks\Schemas\DetailMasukStikForm;
use App\Filament\Resources\DetailMasukStiks\Tables\DetailMasukStiksTable;
use App\Models\DetailMasukStik;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DetailMasukStikResource extends Resource
{
    protected static ?string $model = DetailMasukStik::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }


    public static function form(Schema $schema): Schema
    {
        return DetailMasukStikForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DetailMasukStiksTable::configure($table);
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
            'index' => ListDetailMasukStiks::route('/'),
            'create' => CreateDetailMasukStik::route('/create'),
            'edit' => EditDetailMasukStik::route('/{record}/edit'),
        ];
    }
}
