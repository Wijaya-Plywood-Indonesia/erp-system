<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Filament\Resources\DetailMesins\Schemas\DetailMesinForm;
use App\Filament\Resources\DetailMesins\Tables\DetailMesinsTable;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;


class DetailMesinsRelationManager extends RelationManager
{
    protected static ?string $title = 'Mesin Dryer';
    protected static string $relationship = 'detailMesins';

    // FUNGSI BARU UNTUK MEMUNCULKAN TOMBOL DI HALAMAN VIEW
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DetailMesinForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailMesinsTable::configure($table);
    }
}
