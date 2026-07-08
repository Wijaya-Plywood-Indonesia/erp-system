<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\BahanHotPresses\Schemas\BahanHotPressForm;
use App\Filament\Resources\BahanHotPresses\Tables\BahanHotPressesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BahanHotPressRelationManager extends RelationManager
{
    protected static ?string $title = 'Bahan Hot Press';
    protected static string $relationship = 'BahanHotPress';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return BahanHotPressForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return BahanHotPressesTable::configure($table);
    }
}
