<?php

namespace App\Filament\Resources\PegawaiPotAfJoints\Tables;

use Filament\Actions\Action;
use App\Filament\Support\PindahPegawaiTableActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PegawaiPotAfJointsTable
{
    public static function configure(Table $table): Table
    {
        // Tanggal produksi yang sedang dibuka, dipakai fitur Pindah Pegawai untuk
        // mencari/membuat produksi tujuan pada tanggal yang sama.
        $tanggalProduksi = fn ($livewire) => $livewire->getOwnerRecord()->tanggal_produksi;

        return $table
            ->columns([
                TextColumn::make('pegawai.nama_pegawai')
                    ->label('Pegawai')
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

                TextColumn::make('tugas')
                    ->label('Tugas')
                    ->searchable(),

                TextColumn::make('masuk')
                    ->label('Masuk')
                    ->dateTime('H:i'),

                TextColumn::make('pulang')
                    ->label('Pulang')
                    ->dateTime('H:i'),

                TextColumn::make('ijin')
                    ->label('Izin'),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->limit(30),
            ])
            ->filters([
                //
            ])

            ->headerActions([
                // Create Action — HILANG jika status sudah divalidasi
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                // Edit Action — HILANG jika status sudah divalidasi
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                // Delete Action — HILANG jika status sudah divalidasi
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                // ➕ Tambah / Edit Ijin & Keterangan
                Action::make('aturIjin')
                    ->label(fn($record) => $record->ijin ? 'Edit Ijin' : 'Tambah Ijin')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        TextInput::make('ijin')->label('Izin'),
                        Textarea::make('ket')->label('Keterangan'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'ijin' => $data['ijin'],
                            'ket'  => $data['ket'],
                        ]);
                    })
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
                PindahPegawaiTableActions::recordAction('pot_af_joint', $tanggalProduksi),
                PindahPegawaiTableActions::batalAction('pot_af_joint'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    PindahPegawaiTableActions::bulkAction('pot_af_joint', $tanggalProduksi),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}