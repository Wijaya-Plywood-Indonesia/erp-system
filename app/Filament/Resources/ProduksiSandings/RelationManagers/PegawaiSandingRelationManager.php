<?php

namespace App\Filament\Resources\ProduksiSandings\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\PegawaiSandings\Schemas\PegawaiSandingForm;
use App\Filament\Resources\PegawaiSandings\Tables\PegawaiSandingsTable;

class PegawaiSandingRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'pegawaiSandings';
    public function isReadOnly(): bool
    {
        return false;
    }
    public function form(Schema $schema): Schema
    {
        return PegawaiSandingForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiSandingsTable::configure($table);
    }
}
