<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\ValidasiHps\Schemas\ValidasiHpForm;
use App\Filament\Resources\ValidasiHps\Tables\ValidasiHpsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ValidasiHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'ValidasiHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiHpsTable::configure($table);
}
}
