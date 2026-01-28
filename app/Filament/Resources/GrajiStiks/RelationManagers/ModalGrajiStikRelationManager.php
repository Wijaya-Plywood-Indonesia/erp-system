<?php

namespace App\Filament\Resources\GrajiStiks\RelationManagers;

use App\Filament\Resources\ModalGrajiStiks\Schemas\ModalGrajiStikForm;
use App\Filament\Resources\ModalGrajiStiks\Tables\ModalGrajiStiksTable;
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

class ModalGrajiStikRelationManager extends RelationManager
{
    protected static string $relationship = 'modalGrajiStik';

    public function form(Schema $schema): Schema
    {
        return ModalGrajiStikForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ModalGrajiStiksTable::configure($table);
    }
}
