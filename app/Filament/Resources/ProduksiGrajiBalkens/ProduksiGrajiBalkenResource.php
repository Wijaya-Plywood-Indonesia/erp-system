<?php

namespace App\Filament\Resources\ProduksiGrajiBalkens;

use App\Filament\Resources\ProduksiGrajiBalkens\Pages\CreateProduksiGrajiBalken;
use App\Filament\Resources\ProduksiGrajiBalkens\Pages\EditProduksiGrajiBalken;
use App\Filament\Resources\ProduksiGrajiBalkens\Pages\ListProduksiGrajiBalkens;
use App\Filament\Resources\ProduksiGrajiBalkens\Pages\ViewProduksiGrajiBalken;
use App\Filament\Resources\ProduksiGrajiBalkens\Schemas\ProduksiGrajiBalkenForm;
use App\Filament\Resources\ProduksiGrajiBalkens\Schemas\ProduksiGrajiBalkenInfoList;
use App\Filament\Resources\ProduksiGrajiBalkens\Tables\ProduksiGrajiBalkensTable;
use App\Models\ProduksiGrajiBalken;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProduksiGrajiBalkenResource extends Resource
{
    protected static ?string $model = ProduksiGrajiBalken::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|UnitEnum|null $navigationGroup = 'Graji Balken';

    protected static ?string $recordTitleAttribute = 'no';

    public static function form(Schema $schema): Schema
    {
        return ProduksiGrajiBalkenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProduksiGrajiBalkensTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProduksiGrajiBalkenInfoList::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PegawaiGrajiBalkenRelationManager::class,
            RelationManagers\HasilGrajiBalkenRelationManager::class,
            RelationManagers\ValidasiGrajiBalkenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProduksiGrajiBalkens::route('/'),
            'create' => CreateProduksiGrajiBalken::route('/create'),
            'view' => ViewProduksiGrajiBalken::route('/{record}'),
            'edit' => EditProduksiGrajiBalken::route('/{record}/edit'),
        ];
    }
}
