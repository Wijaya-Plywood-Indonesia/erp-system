<?php

namespace App\Filament\Resources\ProduksiSandings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\ValidasiSandings\Schemas\ValidasiSandingForm;
use App\Filament\Resources\ValidasiSandings\Tables\ValidasiSandingsTable;

class ValidasiSandingRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi';
    protected static string $relationship = 'validasiSanding';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return ValidasiSandingForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiSandingsTable::configure($table);
    }
}
