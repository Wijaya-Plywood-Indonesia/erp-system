<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\PlatformBahanHps\Schemas\PlatformBahanHpForm;
use App\Filament\Resources\PlatformBahanHps\Tables\PlatformBahanHpsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PlatformBahanHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Bahan Platform';
    protected static string $relationship = 'platformBahanHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PlatformBahanHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PlatformBahanHpsTable::configure($table);

    }
}
