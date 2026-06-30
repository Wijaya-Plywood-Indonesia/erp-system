<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\TriplekHasilHps\Schemas\TriplekHasilHpForm;
use App\Filament\Resources\TriplekHasilHps\Tables\TriplekHasilHpsTable;
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

class TriplekHasilHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil Triplek';
    protected static string $relationship = 'triplekHasilHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return TriplekHasilHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return TriplekHasilHpsTable::configure($table);
    }
}
