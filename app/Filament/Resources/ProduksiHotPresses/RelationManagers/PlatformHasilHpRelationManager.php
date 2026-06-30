<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\PlatformHasilHps\Schemas\PlatformHasilHpForm;
use App\Filament\Resources\PlatformHasilHps\Tables\PlatformHasilHpsTable;
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

class PlatformHasilHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Hasil Platform';
    protected static string $relationship = 'platformHasilHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return PlatformHasilHpForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return PlatformHasilHpsTable::configure($table);
    }
}
