<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Filament\Resources\HasilPilihPlywoods\Schemas\HasilPilihPlywoodForm;
use App\Filament\Resources\HasilPilihPlywoods\Tables\HasilPilihPlywoodsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class HasilPilihPlywoodRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilPilihPlywood';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
{
    // Panggil static method dan masukkan hasilnya ke dalam method ->components()
    return $schema
        ->components(HasilPilihPlywoodForm::configure());
}

    public function table(Table $table): Table
    {
        return HasilPilihPlywoodsTable::configure($table);
    }
}