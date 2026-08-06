<?php

namespace App\Filament\Resources\PengajuanBarangs\Pages;

use App\Filament\Resources\PengajuanBarangs\PengajuanBarangResource;
use App\Models\PengajuanBarang;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Filament\Schemas\Components\Tabs\Tab as TabsTab;
use Illuminate\Database\Eloquent\Builder;

class ListPengajuanBarangs extends ListRecords
{
    protected static string $resource = PengajuanBarangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Ajukan Barang'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => TabsTab::make('Semua')
                ->badge(PengajuanBarang::count()),

            'menunggu' => TabsTab::make('Menunggu Persetujuan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function (Builder $q) {
                    $q->where('status_pengawas_produksi', 'menunggu')
                        ->orWhere('status_kepala_produksi', 'menunggu')
                        ->orWhere('status_admin_barang', 'menunggu');
                }))
                ->badge(
                    PengajuanBarang::where(function (Builder $q) {
                        $q->where('status_pengawas_produksi', 'menunggu')
                            ->orWhere('status_kepala_produksi', 'menunggu')
                            ->orWhere('status_admin_barang', 'menunggu');
                    })->count()
                )
                ->badgeColor('warning'),

            'hari_ini' => TabsTab::make('Hari Ini')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('tanggal', today()))
                ->badge(
                    PengajuanBarang::whereDate('tanggal', today())->count()
                )
                ->badgeColor('info'),
        ];
    }
}