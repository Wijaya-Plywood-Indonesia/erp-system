<?php

namespace App\Filament\Resources\ProduksiPotSikus\RelationManagers;

use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Schemas\DetailBarangDikerjakanPotSikuForm;
use App\Filament\Resources\DetailBarangDikerjakanPotSikus\Tables\DetailBarangDikerjakanPotSikusTable;
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

class DetailBarangDikerjakanPotSikuRelationManager extends RelationManager
{
    protected static ?string $title = 'Pot Siku';
    protected static string $relationship = 'DetailBarangDikerjakanPotSiku';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return DetailBarangDikerjakanPotSikuForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailBarangDikerjakanPotSikusTable::configure($table);
    }
}
