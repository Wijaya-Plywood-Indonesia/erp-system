<?php

namespace App\Filament\Resources\DetailHasils\Tables;

use App\Models\DetailHasil;
use App\Models\StokVeneerKering;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DetailHasilsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                DetailHasil::query()->with('stokMasuk')
            )
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk ? 'Sudah Serah' : 'Belum Serah'),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Isi')
                    ->sortable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),

                TextColumn::make('produksiDryer.tanggal_produksi')
                    ->label('Tgl Produksi')
                    ->date('d/m/Y'),
            ])
            ->recordActions([
                Action::make('serahKeGudang')
                    ->label('Serah')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => (bool) $record->stokMasuk)
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            $m3 = ($record->ukuran->panjang * $record->ukuran->lebar * $record->ukuran->tebal * $record->isi) / 1000000;

                            $lastSnapshot = StokVeneerKering::snapshotTerakhir(
                                $record->id_ukuran,
                                $record->id_jenis_kayu,
                                $record->kw
                            );

                            StokVeneerKering::create([
                                'id_detail_hasil_dryer' => $record->id,
                                'id_ukuran' => $record->id_ukuran,
                                'id_jenis_kayu' => $record->id_jenis_kayu,
                                'kw' => $record->kw,
                                'jenis_transaksi' => 'masuk',
                                'tanggal_transaksi' => now(),
                                'qty' => $record->isi,
                                'm3' => $m3,
                                'stok_m3_sebelum' => $lastSnapshot['stok_m3'],
                                'stok_m3_sesudah' => $lastSnapshot['stok_m3'] + $m3,
                                'keterangan' => "MASUK DARI PRODUKSI: No. Palet {$record->no_palet}",
                            ]);

                            $record->update(['is_diserahkan' => true]);
                        });

                        Notification::make()
                            ->title('Palet Berhasil Diserahkan')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(fn($record) => (bool) $record->stokMasuk),

                DeleteAction::make()
                    ->hidden(fn($record) => (bool) $record->stokMasuk),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}