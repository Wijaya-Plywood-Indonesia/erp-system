<?php

namespace App\Filament\Resources\ProduksiStiks\RelationManagers;

use App\Filament\Resources\DetailMasukStiks\Schemas\DetailMasukStikForm;
use App\Filament\Resources\DetailMasukStiks\Tables\DetailMasukStiksTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailMasukStikRelationManager extends RelationManager
{
    protected static ?string $title = 'Modal';
    protected static string $relationship = 'detailMasukStik';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $idProduksiStik = $this->getOwnerRecord()->id;
        return DetailMasukStikForm::configure($schema, $idProduksiStik);
    }

    public function table(Table $table): Table
    {
        return DetailMasukStiksTable::configure($table);
    }
}
