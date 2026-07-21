<?php

namespace App\Filament\Resources\HasilSandingJoints\Tables;

use App\Models\SerahTerimaVeneerKering;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class HasilSandingJointsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with([
                'jenisKayu',
                'ukuran',
                'serahTerimaVeneerKering', // eager load untuk cek status serah
            ]))
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->formatStateUsing(fn($state) => 'SJ-' . $state)
                    ->color(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'gray';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;

                        if (! $serahTerima) {
                            return 'Belum Serah';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima';
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('Ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->searchable(false)
                    ->placeholder('Ukuran'),

                TextColumn::make('kw')
                    ->label('Kualitas (KW)')
                    ->searchable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah'),

                // Kolom status serah — informatif
                TextColumn::make('status_serah')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'Sudah Diserahkan' : 'Sudah Diterima';
                    })
                    ->color(fn($state) => match ($state) {
                        'Sudah Diterima' => 'success',
                        'Sudah Diserahkan' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Create Action — HILANG jika status sudah divalidasi
                CreateAction::make()
                    ->hidden(
                        fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                // Tombol Serah — muncul kalau belum pernah diserahkan, langsung ke Gudang Veneer Jadi
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Veneer Jadi ini ke Gudang?')
                    ->modalDescription('Pastikan data berikut sudah sesuai sebelum diserahkan.')
                    ->modalContent(function ($record) {
                        $jenisKayu = $record->jenisKayu?->nama_kayu ?? '-';
                        $ukuranModel = $record->ukuran;
                        $ukuran = $ukuranModel
                            ? "{$ukuranModel->panjang} x {$ukuranModel->lebar} x {$ukuranModel->tebal}"
                            : '-';

                        return new HtmlString(<<<HTML
    <div class="space-y-2 text-sm">
        <div class="grid grid-cols-3 gap-1">
            <span class="font-medium text-gray-500">No. Palet</span>
            <span class="col-span-2">: {$record->no_palet}</span>

            <span class="font-medium text-gray-500">Jenis Kayu</span>
            <span class="col-span-2">: {$jenisKayu}</span>

            <span class="font-medium text-gray-500">Ukuran</span>
            <span class="col-span-2">: {$ukuran}</span>

            <span class="font-medium text-gray-500">Kualitas (KW)</span>
            <span class="col-span-2">: {$record->kw}</span>

            <span class="font-medium text-gray-500">Jumlah</span>
            <span class="col-span-2">: {$record->jumlah}</span>
        </div>
    </div>
HTML);
                    })
                    ->visible(fn($record) => ! $record->serahTerimaVeneerKering)
                    ->action(function ($record) {
                        try {
                            DB::transaction(function () use ($record) {
                                SerahTerimaVeneerKering::create([
                                    'id_detail_hasil' => null,
                                    'id_detail_bongkar_kedi' => null,
                                    'id_hasil_sanding_joint' => $record->id,   // ← diperbaiki, sebelumnya salah pakai id_detail_hasil
                                    'tipe_sumber' => 'sanding_joint',
                                    'id_produksi_repair' => null,
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'jenis_terima' => 'jadi',
                                    'status' => 'Serah Veneer',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaVeneerKering');
                            $record->refresh();

                            Notification::make()
                                ->title('Veneer Jadi Berhasil Diserahkan')
                                ->body('Siap diterima Gudang Veneer Jadi.')
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
                // Edit Action — HILANG jika sudah diterima atau sudah divalidasi
                EditAction::make()
                    ->hidden(function ($livewire, $record) {
                        $serahTerima = $record->serahTerimaVeneerKering;
                        $sudahDiterima = $serahTerima && $serahTerima->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),

                // Delete Action — HILANG jika sudah diserahkan atau sudah divalidasi
                DeleteAction::make()
                    ->hidden(
                        fn($livewire, $record) => $record->serahTerimaVeneerKering
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
