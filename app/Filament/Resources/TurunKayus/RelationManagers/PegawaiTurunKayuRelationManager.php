<?php

namespace App\Filament\Resources\TurunKayus\RelationManagers;
use App\Filament\Resources\PegawaiTurunKayus\Schemas\PegawaiTurunKayuForm;
use App\Filament\Resources\PegawaiTurunKayus\Tables\PegawaiTurunKayusTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PegawaiTurunKayuRelationManager extends RelationManager
{
    protected static string $relationship = 'pegawaiTurunKayu';

    public function form(Schema $schema): Schema
    {
        return PegawaiTurunKayuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PegawaiTurunKayusTable::configure($table);
    }
    public function isReadOnly(): bool
    {
        return false;
    }
}
