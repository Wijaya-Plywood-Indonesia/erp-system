<?php

namespace App\Filament\Resources\Bks\Pages;

use App\Filament\Resources\Bks\BkResource;
use App\Models\Bk;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListBks extends ListRecords
{
    protected static string $resource = BkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Buat BK')];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')),
            'di_luar' => Tab::make('Barang di luar')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'divalidasi'))
                ->badge(Bk::where('status', 'divalidasi')->count() ?: null)
                ->badgeColor('danger'),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'selesai')),
        ];
    }
}