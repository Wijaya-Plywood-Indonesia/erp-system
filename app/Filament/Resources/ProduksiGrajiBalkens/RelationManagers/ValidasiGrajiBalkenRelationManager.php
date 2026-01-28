<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens\RelationManagers;

use App\Filament\Resources\ValidasiGrajiBalkens\Schemas\ValidasiGrajiBalkenForm;
use App\Filament\Resources\ValidasiGrajiBalkens\Tables\ValidasiGrajiBalkensTable;
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

class ValidasiGrajiBalkenRelationManager extends RelationManager
{
    protected static string $relationship = 'ValidasiGrajiBalken';
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return ValidasiGrajiBalkenForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ValidasiGrajiBalkensTable::configure($table);
    }
}
