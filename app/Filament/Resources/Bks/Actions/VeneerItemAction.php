<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Services\BkBm\BkBmVeneerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/** Tombol "Tambah Barang Veneer" di tabel barang BK dan BM. Stok baru berubah saat Validasi Nota. */
class VeneerItemAction
{
    public static function make(): Action
    {
        return Action::make('tambah_veneer')
            ->label('Tambah Barang Veneer')
            ->icon('heroicon-o-plus')
            ->color('gray')
            ->visible(fn ($livewire) => ! $livewire->getOwnerRecord()->terkunci())
            ->schema([
                Select::make('tipe_veneer')->label('Jenis veneer')->required()->native(false)
                    ->options(['basah' => 'Basah', 'kering' => 'Kering', 'jadi' => 'Jadi']),
                Select::make('id_jenis_kayu')->label('Jenis kayu')->required()->searchable()
                    ->options(fn () => JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')),
                Select::make('kw')->label('KW')->required()->native(false)
                    ->options(['1' => 'KW 1', '2' => 'KW 2', '3' => 'KW 3', '4' => 'KW 4']),
                Select::make('id_ukuran')->label('Ukuran')->required()->searchable()
                    ->options(fn () => Ukuran::orderBy('panjang')->get()->pluck('dimensi', 'id')),
                TextInput::make('qty')->label('Jumlah (lembar)')->numeric()->integer()->minValue(1)->required(),
                TextInput::make('keterangan')->maxLength(255),
            ])
            ->action(function (array $data, $livewire) {
                try {
                    app(BkBmVeneerService::class)->tambahItem($livewire->getOwnerRecord(), $data);
                    Notification::make()->title('Barang veneer ditambahkan')->success()->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
                }
            });
    }
}
