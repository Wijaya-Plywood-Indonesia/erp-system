<?php

namespace App\Filament\Resources\ProduksiKedis\Pages;

use App\Filament\Resources\ProduksiKedis\ProduksiKediResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProduksiKedis extends ListRecords
{
    protected static string $resource = ProduksiKediResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua Data'),
            'masuk' => Tab::make('Masuk')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'masuk'))
                ->icon('heroicon-m-arrow-down-left')
                ->badge(fn() => $this->getModel()::where('status', 'masuk')->count()),
            'bongkar' => Tab::make('Bongkar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'bongkar'))
                ->icon('heroicon-m-arrow-up-right')
                ->badge(fn() => $this->getModel()::where('status', 'bongkar')->count()),
        ];
    }
}
