<?php

namespace App\Filament\Resources\PegawaiGrajiStiks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class PegawaiGrajiStiksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pegawai')
                    ->searchable(),

                TextColumn::make('jam_masuk')
                    ->label('Masuk')
                    ->dateTime('H:i'),

                TextColumn::make('jam_pulang')
                    ->label('Pulang')
                    ->dateTime('H:i'),

                TextColumn::make('ijin')
                    ->label('Izin')
                    ->badge() // Menambah badge agar lebih rapi
                    ->color(fn(string $state): string => match ($state) {
                        'sakit' => 'danger',
                        'izin' => 'warning',
                        'cuti' => 'info',
                        'alpha' => 'gray',
                        default => 'success',
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'Hadir')),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(20)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // 🔒 Lock Create Action
                CreateAction::make()
                    ->label('Tambah Pegawai')
                    ->icon('heroicon-o-plus')
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
            ])
            ->recordActions([
                // 🔒 Lock Custom Action Ijin & Keterangan
                Action::make('ijin_keterangan')
                    ->label('Ijin & Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('warning')
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked())
                    ->form([
                        Select::make('ijin')
                            ->label('Jenis Ijin')
                            ->options([
                                '' => 'Tidak Ada Ijin (Hadir)',
                                'sakit' => 'Sakit',
                                'izin' => 'Izin Pribadi',
                                'cuti' => 'Cuti',
                                'alpha' => 'Tanpa Keterangan',
                            ])
                            ->native(false)
                            ->default(fn($record) => $record->ijin),

                        Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
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

                // 🔒 Lock Standard Actions
                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->isLocked()),
                ]),
            ]);
    }
}
