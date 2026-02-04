<?php

namespace App\Filament\Resources\ProduksiPotJeleks\RelationManagers;

use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Schemas\DetailBarangDikerjakanPotJelekForm;
use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Tables\DetailBarangDikerjakanPotJeleksTable;
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

class DetailBarangDikerjakanPotJelekRelationManager extends RelationManager
{
    protected static ?string $title = 'Pot Jelek';
    protected static string $relationship = 'DetailBarangDikerjakanPotJelek';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanPotJelekForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailBarangDikerjakanPotJeleksTable::configure($table);
    }
}
