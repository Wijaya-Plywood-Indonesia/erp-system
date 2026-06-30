<?php

namespace App\Filament\Resources\HasilGrajiBalkens;

use App\Filament\Resources\HasilGrajiBalkens\Pages\CreateHasilGrajiBalken;
use App\Filament\Resources\HasilGrajiBalkens\Pages\EditHasilGrajiBalken;
use App\Filament\Resources\HasilGrajiBalkens\Pages\ListHasilGrajiBalkens;
use App\Filament\Resources\HasilGrajiBalkens\Schemas\HasilGrajiBalkenForm;
use App\Filament\Resources\HasilGrajiBalkens\Tables\HasilGrajiBalkensTable;
use App\Models\HasilGrajiBalken;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HasilGrajiBalkenResource extends Resource
{
    protected static ?string $model = HasilGrajiBalken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'no';
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HasilGrajiBalkenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HasilGrajiBalkensTable::configure($table);
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
            'index' => ListHasilGrajiBalkens::route('/'),
            'create' => CreateHasilGrajiBalken::route('/create'),
            'edit' => EditHasilGrajiBalken::route('/{record}/edit'),
        ];
    }
}
