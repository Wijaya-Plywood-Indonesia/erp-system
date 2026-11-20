<?php

namespace App\Filament\Resources\Bks\Pages;

use App\Filament\Resources\Bks\BkResource;
use App\Models\Bk;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListBks extends ListRecords
{
    protected static string $resource = BkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_belum_kembali')->label('Export Belum Kembali')->icon('heroicon-o-arrow-down-tray')->color('danger')
                ->modalHeading('Export Barang Belum Kembali')->modalSubmitActionLabel('Unduh Excel')
                ->modalDescription('Pilih rentang tanggal keluar. Kosongkan kedua tanggal untuk semua yang belum kembali.')
                ->schema([
                    DatePicker::make('dari')->label('Keluar dari tanggal'),
                    DatePicker::make('sampai')->label('Sampai tanggal'),
                    Select::make('jenis')->label('Jenis (kosongkan = BKL dan BKK)')->multiple()
                        ->options(['bkl' => 'BKL – Barang Kita di Luar', 'bkk' => 'BKK – Barang Keluar (Pinjam)']),
                ])
                ->action(fn (array $data) => redirect()->route('bkbm.export.belum-kembali', array_filter([
                    'dari' => $data['dari'] ?? null, 'sampai' => $data['sampai'] ?? null, 'jenis' => $data['jenis'] ?? null,
                ]))),
            Action::make('export_rekap')->label('Export Rekap')->icon('heroicon-o-arrow-down-tray')->color('success')
                ->modalHeading('Export Rekap BK')->modalSubmitActionLabel('Unduh Excel')
                ->schema([
                    DatePicker::make('dari')->label('Dari tanggal')->default(now()->startOfMonth())->required(),
                    DatePicker::make('sampai')->label('Sampai tanggal')->default(today())->required(),
                    Select::make('jenis')->label('Jenis (kosongkan = semua)')->multiple()
                        ->options(collect(config('bkbm.kategori_bk'))->map(fn ($k) => $k['label'])->all()),
                ])
                ->action(fn (array $data) => redirect()->route('bkbm.export.bk', [
                    'dari' => $data['dari'], 'sampai' => $data['sampai'], 'jenis' => $data['jenis'] ?? [],
                ])),
            CreateAction::make()->label('Buat BK'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua'),
            'draft' => Tab::make('Draft')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'draft')),
            'di_luar' => Tab::make('Barang di luar')
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['divalidasi', 'dipinjam']))
                ->badge(Bk::whereIn('status', ['divalidasi', 'dipinjam'])->count() ?: null)
                ->badgeColor('danger'),
            'selesai' => Tab::make('Selesai')
                ->modifyQueryUsing(fn ($query) => $query->where('status', 'selesai')),
        ];
    }
}