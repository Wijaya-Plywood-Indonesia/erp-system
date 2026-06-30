<?php

namespace App\Filament\Resources\OpnameStoks;

use App\Filament\Resources\OpnameStoks\Pages;
use App\Filament\Resources\OpnameStoks\Schemas\OpnameStokForm;
use App\Models\BarangSetengahJadiHp;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;

class OpnameStokResource extends Resource
{
    protected static ?string $model = BarangSetengahJadiHp::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Opname Stok';
    protected static UnitEnum|string|null $navigationGroup = 'Opname';

    public static function form(Schema $schema): Schema
    {
        return OpnameStokForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpnameStoks::route('/'),
        ];
    }
}
