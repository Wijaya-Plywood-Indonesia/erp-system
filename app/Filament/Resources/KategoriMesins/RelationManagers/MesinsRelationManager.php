<?php

namespace App\Filament\Resources\KategoriMesins\RelationManagers;

use App\Filament\Resources\Mesins\MesinResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class MesinsRelationManager extends RelationManager
{
    protected static string $relationship = 'mesins';

    protected static ?string $relatedResource = MesinResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
    //    protected static bool $isLazy = false;

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        // hanya tampil di halaman "View" (bukan "Edit" atau "Create")
        return str($pageClass)->contains('View');
    }
}
