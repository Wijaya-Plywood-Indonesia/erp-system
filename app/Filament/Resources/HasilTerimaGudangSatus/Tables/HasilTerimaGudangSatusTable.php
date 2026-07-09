<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Tables;

use App\Models\SerahTerimaGudangSatu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class HasilTerimaGudangSatusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grade.nama_grade')
                    ->label('Grade')
                    ->getStateUsing(
                        fn ($record) => ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            .' | '.
                            ($record->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn ($record) => $record->ukuran?->dimensi ?? '-')
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->searchable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        return $serah->diterima_oleh === '-' ? 'Menunggu Diterima' : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serah')
                    ->label('Serah (Nyusup)')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan barang ini untuk Nyusup?')
                    ->modalDescription(fn ($record) => 'Jumlah: '.($record->jumlah ?? 0).' pcs. Barang akan masuk antrian penerimaan dengan tujuan Nyusup.')
                    ->visible(fn ($record) => ! $record->serahTerimaGudangSatu)
                    ->action(function ($record) {
                        try {
                            SerahTerimaGudangSatu::create([
                                'id_hasil_terima_gudang_satu' => $record->id,
                                'tujuan' => 'nyusup',
                                'diserahkan_oleh' => Auth::user()->name,
                                'diterima_oleh' => '-',
                                'status' => 'Menunggu',
                            ]);

                            Notification::make()
                                ->title('Berhasil diserahkan (Nyusup)')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($record, $livewire) => $record->serahTerimaGudangSatu
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
