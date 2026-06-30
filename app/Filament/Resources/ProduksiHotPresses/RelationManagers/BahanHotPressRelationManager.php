<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\BahanHotPresses\Schemas\BahanHotPressForm;
use App\Filament\Resources\BahanHotPresses\Tables\BahanHotPressesTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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
