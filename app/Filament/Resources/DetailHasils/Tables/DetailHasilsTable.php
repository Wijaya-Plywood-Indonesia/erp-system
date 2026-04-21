<?php

namespace App\Filament\Resources\DetailHasils\Tables;

use App\Models\DetailHasil;
use App\Services\SerahHasilDryerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;

class DetailHasilsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                DetailHasil::query()->with(['stokMasuk', 'ukuran', 'jenisKayu', 'produksiDryer'])
            )
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk
                        ? 'Sudah Serah (' . $record->stokMasuk->tanggal_transaksi->format('d/m/Y') . ')'
                        : 'Belum Serah'),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Isi (Lbr)')
                    ->sortable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),

                TextColumn::make('produksiDryer.tanggal_produksi')
                    ->label('Tgl Produksi')
                    ->date('d/m/Y'),
            ])
            ->recordActions([
                Action::make('serahKeGudang')
                    ->label('Serahkan Hasil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Palet ke Gudang Kering?')
                    ->modalDescription('Setelah diserahkan, data ini akan masuk ke stok gudang dan saldo lembar/m3 akan bertambah.')
                    ->modalSubmitActionLabel('Ya, Serahkan Sekarang')
                    ->visible(fn($record) => is_null($record->stokMasuk))
                    ->action(function (DetailHasil $record) {
                        try {
                            app(SerahHasilDryerService::class)->serahkan($record);

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet {$record->no_palet} telah dipindahkan ke stok gudang.")
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Terjadi Kesalahan Sistem')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(fn($record) => !is_null($record->stokMasuk)),

                DeleteAction::make()
                    ->hidden(fn($record) => !is_null($record->stokMasuk)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}