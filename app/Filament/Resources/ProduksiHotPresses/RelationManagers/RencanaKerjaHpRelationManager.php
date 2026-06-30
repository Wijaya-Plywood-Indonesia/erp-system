<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Filament\Resources\RencanaKerjaHps\Schemas\RencanaKerjaHpForm;
use App\Filament\Resources\RencanaKerjaHps\Tables\RencanaKerjaHpsTable;
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

class RencanaKerjaHpRelationManager extends RelationManager
{
    protected static ?string $title = 'Rencana Produksi';
    protected static string $relationship = 'RencanaKerjaHp';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    { {
            return RencanaKerjaHpForm::configure($schema);
        }
    }

    public function table(Table $table): Table
    {
        return RencanaKerjaHpsTable::configure($table);
    }
}
