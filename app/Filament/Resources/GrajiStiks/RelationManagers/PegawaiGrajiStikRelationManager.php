<?php

namespace App\Filament\Resources\GrajiStiks\RelationManagers;

use App\Filament\Resources\PegawaiGrajiStiks\Schemas\PegawaiGrajiStikForm;
use App\Filament\Resources\PegawaiGrajiStiks\Tables\PegawaiGrajiStiksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PegawaiGrajiStikRelationManager extends RelationManager
{
    protected static string $relationship = 'pegawaiGrajiStik';

    public function form(Schema $schema): Schema
    {
        return PegawaiGrajiStikForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiGrajiStiksTable::configure($table);
    }
}
