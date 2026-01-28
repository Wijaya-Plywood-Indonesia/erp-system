<?php

namespace App\Filament\Resources\ValidasiGrajiBalkens;

use App\Filament\Resources\ValidasiGrajiBalkens\Pages\CreateValidasiGrajiBalken;
use App\Filament\Resources\ValidasiGrajiBalkens\Pages\EditValidasiGrajiBalken;
use App\Filament\Resources\ValidasiGrajiBalkens\Pages\ListValidasiGrajiBalkens;
use App\Filament\Resources\ValidasiGrajiBalkens\Schemas\ValidasiGrajiBalkenForm;
use App\Filament\Resources\ValidasiGrajiBalkens\Tables\ValidasiGrajiBalkensTable;
use App\Models\ValidasiGrajiBalken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ValidasiGrajiBalkenResource extends Resource
{
    protected static ?string $model = ValidasiGrajiBalken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ValidasiGrajiBalkenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ValidasiGrajiBalkensTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListValidasiGrajiBalkens::route('/'),
            'create' => CreateValidasiGrajiBalken::route('/create'),
            'edit' => EditValidasiGrajiBalken::route('/{record}/edit'),
        ];
    }
}
