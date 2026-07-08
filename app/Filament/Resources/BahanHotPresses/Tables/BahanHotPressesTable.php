<?php

namespace App\Filament\Resources\BahanHotPresses\Tables;

use Dom\Text;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;

class BahanHotPressesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable(),

                TextColumn::make('jenis')
                    ->label('Tipe')
                    ->state(function ($record) {
                        // Kalau relasi mutasiKeluarPalet berhasil ditemukan,
                        // otomatis berarti sumbernya dari tabel veneer_jadi_mutasi_keluar_palets.
                        // Tidak perlu cek nama gudang, karena kolom itu memang tidak ada di sana.
                        if ($record->mutasiKeluarPalet?->mutasiKeluar) {
                            return 'Veneer';
                        }

                        // Placeholder untuk sumber Platform, kalau nanti relasinya sudah ditambahkan
                        // di model BahanHotpress, misal: $record->mutasiKeluarPlatform
                        // if ($record->mutasiKeluarPlatform) {
                        //     return 'Platform';
                        // }Gudang

                        return '-';
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Veneer'   => 'success',
                        'Platform' => 'info',
                        default    => 'gray',
                    }),

                /*
                 * JENIS BARANG
                 */
                TextColumn::make('mutasiKeluarPalet.mutasiKeluar.jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),

                /*
                 * GRADE
                 */
                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn($record) => $record->mutasiKeluarPalet?->mutasiKeluar?->kw_grade ?? '-'),

                /*
                 * UKURAN
                 */
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        $mk = $record->mutasiKeluarPalet?->mutasiKeluar;

                        if (! $mk) {
                            return '-';
                        }

                        $panjang = (float) $mk->panjang + 0;
                        $lebar   = (float) $mk->lebar + 0;
                        $tebal   = (float) $mk->tebal + 0;

                        return "{$panjang} x {$lebar} x {$tebal}";
                    }),

                /*
                 * ISI
                 */
                TextColumn::make('isi')
                    ->label('Jumlah Lembar'),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->wrap()
                    ->limit(50)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('ket')
                    ->label('Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Textarea::make('ket')
                            ->label('Keterangan')
                            ->rows(3)
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ket' => $data['ket'],
                        ]);

                        Notification::make()
                            ->title('Keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Keterangan ")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
