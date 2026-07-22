<?php

namespace App\Filament\Resources\HasilPilihVeneers\Tables;

use Filament\Actions\Action;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;

class HasilPilihVeneersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn($query) =>
                $query->with([
                    'modalPilihVeneer.stokVeneerJadi.jenisKayu',
                    'pegawaiPilihVeneers.pegawai',
                    'diserahkanOleh',
                ])
            )
            // GROUPING BERDASARKAN PEGAWAI
            ->groups([
                Group::make('id')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(function ($record) {
                        if ($record->pegawaiPilihVeneers->isEmpty()) {
                            return 'Pegawai: -';
                        }
                        return 'Pegawai: ' . $record->pegawaiPilihVeneers
                            ->pluck('pegawai.nama_pegawai')
                            ->implode(' & ');
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id')
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('modalPilihVeneer.stokVeneerJadi')
                    ->label('Ukuran Modal')
                    ->getStateUsing(function ($record) {
                        $stok = $record->modalPilihVeneer?->stokVeneerJadi;
                        if (!$stok) return '-';

                        $panjang = floatval($stok->panjang);
                        $lebar = floatval($stok->lebar);
                        $tebal = floatval($stok->tebal);

                        return "{$panjang} x {$lebar} x {$tebal}";
                    })
                    ->description(function ($record) {
                        $kayu = $record->modalPilihVeneer?->stokVeneerJadi?->jenisKayu?->nama_kayu ?? '-';
                        $kwAsal = $record->modalPilihVeneer?->stokVeneerJadi?->kw_grade ?? '-';
                        return "Bahan: {$kayu} (KW Asal: {$kwAsal})";
                    }),

                TextColumn::make('kw')
                    ->label('KW Hasil')
                    ->badge(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),

                TextColumn::make('diserahkan_at')
                    ->label('Status Serah')
                    ->badge()
                    ->state(function ($record) {
                        if (! $record->diserahkan_at) {
                            return 'Belum Diserahkan';
                        }
                        return 'Diserahkan ' . $record->diserahkan_at->translatedFormat('d M Y H:i') . ' oleh ' . ($record->diserahkanOleh?->name ?? '-');
                    })
                    ->color(fn($record) => $record->diserahkan_at ? 'success' : 'warning'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->recordActions([
                Action::make('kirimUlangKeGudang')
                    ->label('Kirim ke Gudang')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(fn($record) => is_null($record->diserahkan_at))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'diserahkan_at' => now(),
                            'diserahkan_by' => auth()->id(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Berhasil Diserahkan')
                            ->body('Baris ini otomatis menunggu di halaman Gudang Veneer Jadi.')
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                DeleteAction::make()
                    ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}
