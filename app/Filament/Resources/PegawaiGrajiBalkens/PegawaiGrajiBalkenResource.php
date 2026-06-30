<?php

namespace App\Filament\Resources\PegawaiGrajiBalkens;

use App\Filament\Resources\PegawaiGrajiBalkens\Pages\CreatePegawaiGrajiBalken;
use App\Filament\Resources\PegawaiGrajiBalkens\Pages\EditPegawaiGrajiBalken;
use App\Filament\Resources\PegawaiGrajiBalkens\Pages\ListPegawaiGrajiBalkens;
use App\Filament\Resources\PegawaiGrajiBalkens\Schemas\PegawaiGrajiBalkenForm;
use App\Filament\Resources\PegawaiGrajiBalkens\Tables\PegawaiGrajiBalkensTable;
use App\Models\PegawaiGrajiBalken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PegawaiGrajiBalkenResource extends Resource
{
    protected static ?string $model = PegawaiGrajiBalken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return PegawaiGrajiBalkenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PegawaiGrajiBalkensTable::configure($table);
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
            'index' => ListPegawaiGrajiBalkens::route('/'),
            'create' => CreatePegawaiGrajiBalken::route('/create'),
            'edit' => EditPegawaiGrajiBalken::route('/{record}/edit'),
        ];
    }
}
