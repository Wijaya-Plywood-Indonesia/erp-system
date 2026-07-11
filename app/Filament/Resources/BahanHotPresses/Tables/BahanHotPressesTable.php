<?php

namespace App\Filament\Resources\BahanHotPresses\Tables;

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
            ->modifyQueryUsing(fn($query) => $query->with([
                'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                'mutasiKeluarPlatform.mutasiKeluar.jenisBarang',
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('jenis')
                    ->label('Tipe')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer' && $record->mutasiKeluarPalet?->mutasiKeluar) {
                            return 'Veneer';
                        }

                        if ($sumber === 'platform' && $record->mutasiKeluarPlatform?->mutasiKeluar) {
                            return 'Platform';
                        }

                        return '-';
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Veneer'   => 'success',
                        'Platform' => 'info',
                        default    => 'gray',
                    }),

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Barang')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer') {
                            return $record->mutasiKeluarPalet?->mutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        if ($sumber === 'platform') {
                            return $record->mutasiKeluarPlatform?->mutasiKeluar?->jenisBarang?->nama_jenis_barang ?? '-';
                        }

                        return '-';
                    }),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        if ($sumber === 'veneer') {
                            return $record->mutasiKeluarPalet?->mutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($sumber === 'platform') {
                            return $record->mutasiKeluarPlatform?->mutasiKeluar?->kw_grade ?? '-';
                        }

                        return '-';
                    }),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        $sumber = $record->sumber
                            ?? ($record->id_mutasi_keluar_palet ? 'veneer' : ($record->id_mutasi_keluar_platform ? 'platform' : null));

                        $mk = $sumber === 'veneer'
                            ? $record->mutasiKeluarPalet?->mutasiKeluar
                            : ($sumber === 'platform' ? $record->mutasiKeluarPlatform?->mutasiKeluar : null);

                        if (! $mk) {
                            return '-';
                        }

                        $panjang = (float) $mk->panjang + 0;
                        $lebar   = (float) $mk->lebar + 0;
                        $tebal   = (float) $mk->tebal + 0;

                        return "{$panjang} x {$lebar} x {$tebal}";
                    }),

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
