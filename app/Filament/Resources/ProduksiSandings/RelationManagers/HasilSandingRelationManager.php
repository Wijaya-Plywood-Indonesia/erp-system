<?php

namespace App\Filament\Resources\ProduksiSandings\RelationManagers;

use App\Filament\Resources\HasilSandings\Schemas\HasilSandingForm;
use App\Filament\Resources\HasilSandings\Tables\HasilSandingsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use aa;
use ss;

class HasilSandingRelationManager extends RelationManager
{

    protected static ?string $title = 'Hasil';
    protected static string $relationship = 'hasilSandings';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return HasilSandingForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilSandingsTable::configure($table);
    }
}
