<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\DetailPegawaiHps\Schemas\DetailPegawaiHpForm;
use App\Filament\Resources\DetailPegawaiHps\Tables\DetailPegawaiHpsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailPegawaiHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai';
    protected static string $relationship = 'detailPegawaiHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DetailPegawaiHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailPegawaiHpsTable::configure($table);
    }
}
