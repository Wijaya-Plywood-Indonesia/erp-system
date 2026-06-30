<?php

namespace App\Filament\Resources\RencanaPegawais\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Textarea;
use Filament\Actions\CreateAction;

class RencanaPegawaisTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pekerja')
                    ->formatStateUsing(
                        fn($record) => $record->pegawai
                        ? $record->pegawai->kode_pegawai . ' - ' . $record->pegawai->nama_pegawai
                        : '—'
                    )
                    ->badge()
                    ->searchable(
                        query: fn($query, $search) => $query->whereHas(
                            'pegawai',
                            fn($q) => $q
                                ->where('nama_pegawai', 'like', "%{$search}%")
                                ->orWhere('kode_pegawai', 'like', "%{$search}%")
                        )
                    ),
                TextColumn::make('nomor_meja')
                    ->label('Nomor Meja')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time()
                    ->sortable(),
                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time()
                    ->sortable(),
                TextColumn::make('ijin')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->default('-')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('ijin_keterangan')
                    ->label('Ijin & Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->form([
                        Select::make('ijin')
                            ->label('Jenis Ijin')
                            ->options([
                                '' => 'Tidak Ada Ijin',
                                'sakit' => 'Sakit',
                                'izin' => 'Izin Pribadi',
                                'cuti' => 'Cuti',
                                'alpha' => 'Tanpa Keterangan',
                            ])
                            ->native(false)
                            ->default(fn($record) => $record->ijin)
                            ->reactive(),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->placeholder('Alasan ijin / keterangan tambahan...')
                            ->default(fn($record) => $record->keterangan),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'] === '' ? null : $data['ijin'],
                            'keterangan' => $data['keterangan'],
                        ]);

                        Notification::make()
                            ->title('Ijin & keterangan berhasil disimpan')
                            ->success()
                            ->send();
                    })
                    ->modalHeading(fn($record) => "Ijin & Keterangan - {$record->pegawai->nama_pegawai}")
                    ->modalSubmitActionLabel('Simpan')
                    ->modalWidth('lg'),
                EditAction::make(),
                Action::make('delete_rencana')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        if ($record->rencanaRepairs()->exists()) {
                            Notification::make()
                                ->title('Tidak bisa dihapus!')
                                ->body('Rencana Pegawai ini masih memiliki data repair yang terkait. Hapus data repair terlebih dahulu.')
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
