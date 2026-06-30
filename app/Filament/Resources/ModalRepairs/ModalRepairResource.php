<?php

namespace App\Filament\Resources\ModalRepairs;

use App\Filament\Resources\ModalRepairs\Pages\CreateModalRepair;
use App\Filament\Resources\ModalRepairs\Pages\EditModalRepair;
use App\Filament\Resources\ModalRepairs\Pages\ListModalRepairs;
use App\Filament\Resources\ModalRepairs\Schemas\ModalRepairForm;
use App\Filament\Resources\ModalRepairs\Tables\ModalRepairsTable;
use App\Models\ModalRepair;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ModalRepairResource extends Resource
{
    protected static ?string $model = ModalRepair::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public static function form(Schema $schema): Schema
    {
        return ModalRepairForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModalRepairsTable::configure($table);
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
            'index' => ListModalRepairs::route('/'),
            'create' => CreateModalRepair::route('/create'),
            'edit' => EditModalRepair::route('/{record}/edit'),
        ];
    }
}
