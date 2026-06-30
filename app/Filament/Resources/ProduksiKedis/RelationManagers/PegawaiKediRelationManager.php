<?php

namespace App\Filament\Resources\ProduksiKedis\RelationManagers;

use App\Filament\Resources\DetailPegawaiKedis\Schemas\DetailPegawaiKediForm;
use App\Filament\Resources\DetailPegawaiKedis\Tables\DetailPegawaiKedisTable;
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

class PegawaiKediRelationManager extends RelationManager
{
    protected static ?string $title = 'Pegawai Kedi';
    protected static string $relationship = 'DetailPegawaiKedi';

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        return $ownerRecord->status === 'bongkar';
    }

    public function form(Schema $schema): Schema
    {
        return DetailPegawaiKediForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return DetailPegawaiKedisTable::configure($table);
    }

    
}
