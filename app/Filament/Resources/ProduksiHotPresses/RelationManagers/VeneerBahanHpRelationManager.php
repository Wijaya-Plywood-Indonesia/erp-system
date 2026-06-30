<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\VeneerBahanHps\Schemas\VeneerBahanHpForm;
use App\Filament\Resources\VeneerBahanHps\Tables\VeneerBahanHpsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class VeneerBahanHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Bahan Veneer';
    protected static string $relationship = 'veneerBahanHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return VeneerBahanHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return VeneerBahanHpsTable::configure($table);
    }
}
