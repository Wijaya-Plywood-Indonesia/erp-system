<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use App\Filament\Resources\ValidasiKedis\Schemas\ValidasiKediForm;
use App\Filament\Resources\ValidasiKedis\Tables\ValidasiKedisTable;
use App\Filament\Resources\ValidasiKedis\ValidasiKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class YesRelationManager extends RelationManager
{
    protected static ?string $title = 'Validasi Produksi';
    protected static string $relationship = 'validasiKedi';

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return $ownerRecord->status !== 'masuk';
    }


    public function form(Schema $schema): Schema
    {
        return ValidasiKediForm::configure($schema);
    }
    public function isReadOnly(): bool
    {
        return false;
    }
    public function table(Table $table): Table
    {
        return ValidasiKedisTable::configure($table);
    }
}
