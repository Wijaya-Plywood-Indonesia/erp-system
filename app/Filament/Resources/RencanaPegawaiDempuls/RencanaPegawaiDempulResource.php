<?php

namespace App\Filament\Resources\RencanaPegawaiDempuls;

use App\Filament\Resources\RencanaPegawaiDempuls\Pages\CreateRencanaPegawaiDempul;
use App\Filament\Resources\RencanaPegawaiDempuls\Pages\EditRencanaPegawaiDempul;
use App\Filament\Resources\RencanaPegawaiDempuls\Pages\ListRencanaPegawaiDempuls;
use App\Filament\Resources\RencanaPegawaiDempuls\Schemas\RencanaPegawaiDempulForm;
use App\Filament\Resources\RencanaPegawaiDempuls\Tables\RencanaPegawaiDempulsTable;
use App\Models\RencanaPegawaiDempul;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RencanaPegawaiDempulResource extends Resource
{
    protected static ?string $model = RencanaPegawaiDempul::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return RencanaPegawaiDempulForm::configure($schema);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return RencanaPegawaiDempulsTable::configure($table);
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
            'index' => ListRencanaPegawaiDempuls::route('/'),
            'create' => CreateRencanaPegawaiDempul::route('/create'),
            'edit' => EditRencanaPegawaiDempul::route('/{record}/edit'),
        ];
    }
}
