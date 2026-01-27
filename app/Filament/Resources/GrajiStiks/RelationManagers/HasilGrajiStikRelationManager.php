<?php

namespace App\Filament\Resources\GrajiStiks\RelationManagers;

use App\Filament\Resources\HasilGrajiStiks\Schemas\HasilGrajiStikForm;
use App\Filament\Resources\HasilGrajiStiks\Tables\HasilGrajiStiksTable;
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

class HasilGrajiStikRelationManager extends RelationManager
{
    protected static string $relationship = 'hasilGrajiStik';

    public function form(Schema $schema): Schema
    {
        return HasilGrajiStikForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return HasilGrajiStiksTable::configure($table);
    }
}
