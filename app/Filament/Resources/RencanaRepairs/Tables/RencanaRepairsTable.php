<?php

namespace App\Filament\Resources\RencanaRepairs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RencanaRepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // 1. UKURAN — PAKAI ACCESSOR dimensi
                TextColumn::make('modalRepairs.ukuran.dimensi')
                    ->label('Ukuran')
                    ->formatStateUsing(fn($state) => $state)
                    ->searchable()
                    ->sortable(),

                // 2. JENIS KAYU
                TextColumn::make('modalRepairs.jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->sortable(),

                // 3. KW
                TextColumn::make('kw')
                    ->label('KW')
                    ->numeric()
                    ->sortable()
                    ->searchable(),

                // 4. MEJA & PEGAWAI — TAMPILKAN DARI RELASI rencanaPegawai
                TextColumn::make('rencanaPegawai.nomor_meja')
                    ->label('No. Meja')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('rencanaPegawai.pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        $record->rencanaPegawai?->pegawai
                        ? "{$record->rencanaPegawai->pegawai->kode_pegawai} - {$state}"
                        : '-'
                    )
                    ->searchable()
                    ->sortable(),

                // 5. WAKTU
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->filters([
                // Tambah filter kalau perlu nanti
            ])

            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Tugas Repair')
                    ->icon('heroicon-o-plus-circle'),
            ])

            ->actions([
                EditAction::make()
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning'),

                Action::make('delete_rencana')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->hasilRepairs()->exists()) {
                            Notification::make()
                                ->title('Tidak bisa dihapus!')
                                ->body('Rencana Repair terhubung dengan data Hasil Repair, pastikan data tersebut bersih (tidak memiliki hasil) sebelum menghapus.')
                                ->warning()
                                ->send();
                            return; // Hentikan delete
                        }

                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Data berhasil dihapus')
                            ->send();
                    })
            ])

            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Terpilih')
                        ->requiresConfirmation(),
                ]),
            ])

            ->emptyStateHeading('Belum ada rencana repair')
            ->emptyStateDescription('Tambahkan tugas repair setelah menugaskan pegawai.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}