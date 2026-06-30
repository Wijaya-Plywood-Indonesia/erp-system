<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use App\Filament\Resources\DetailMasukKedis\DetailMasukKediResource;
use App\Filament\Resources\DetailMasukKedis\Schemas\DetailMasukKediForm;
use App\Filament\Resources\DetailMasukKedis\Tables\DetailMasukKedisTable;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class DetailMasukKediRelationManager extends RelationManager
{
    protected static ?string $title = 'Masuk Kedi';
    protected static string $relationship = 'detailMasukKedi';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return true;
    }


    public function form(Schema $schema): Schema
    {
        return DetailMasukKediForm::configure($schema);
    }
    public function table(Table $table): Table
    {
        return DetailMasukKedisTable::configure($table);
    }

}
