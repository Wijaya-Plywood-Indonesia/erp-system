<?php

namespace App\Filament\Resources\HasilPilihVeneers\Tables;

use App\Models\HasilPilihVeneer;
use App\Services\HasilPilihVeneerService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class HasilPilihVeneersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn ($query) => $query->with([
                    'modalPilihVeneer.stokVeneerJadi.jenisKayu',
                    'modalPilihVeneer.ukuran',
                    'modalPilihVeneer.jenisKayu',
                    'modalPilihVeneer.pegawaiPilihVeneers.pegawai',
                    'diserahkanOleh',
                ])
            )
            // GROUPING BERDASARKAN MODAL
            ->groups([
                Group::make('id_modal_pilih_veneer')
                    ->label('Modal')
                    ->getTitleFromRecordUsing(function ($record) {
                        $modal = $record->modalPilihVeneer;
                        if (! $modal) {
                            return 'Modal: -';
                        }

                        if ($modal->id_stok_veneer_jadi) {
                            $stok = $modal->stokVeneerJadi;
                            $kayu = $stok?->jenisKayu?->nama_kayu ?? '-';
                            $ukuran = $stok ? floatval($stok->panjang).' x '.floatval($stok->lebar).' x '.floatval($stok->tebal) : '-';
                            $jenisLbl = 'Jadi';
                            $kwAsal = $stok?->kw_grade ?? '-';
                        } else {
                            $ukuranRec = $modal->ukuran;
                            $kayu = $modal->jenisKayu?->nama_kayu ?? '-';
                            $ukuran = $ukuranRec ? floatval($ukuranRec->panjang).' x '.floatval($ukuranRec->lebar).' x '.floatval($ukuranRec->tebal) : '-';
                            $jenisLbl = 'Kering';
                            $kwAsal = $modal->kw ?? '-';
                        }

                        $pegawai = $modal->pegawaiPilihVeneers->isNotEmpty()
                            ? $modal->pegawaiPilihVeneers->pluck('pegawai.nama_pegawai')->implode(' & ')
                            : '-';

                        return "Palet #{$modal->no_palet} | [{$jenisLbl}] {$kayu} — {$ukuran} — KW {$kwAsal} | {$modal->jumlah} lbr | 👷 {$pegawai}";
                    })
                    ->collapsible(),
            ])
            ->defaultGroup('id_modal_pilih_veneer')
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('modalPilihVeneer.stokVeneerJadi')
                    ->label('Ukuran Modal')
                    ->getStateUsing(function ($record) {
                        $modal = $record->modalPilihVeneer;
                        if (! $modal) {
                            return '-';
                        }

                        if ($modal->id_stok_veneer_jadi) {
                            $stok = $modal->stokVeneerJadi;
                            if (! $stok) {
                                return '-';
                            }

                            return floatval($stok->panjang).' x '.floatval($stok->lebar).' x '.floatval($stok->tebal);
                        }

                        $ukuran = $modal->ukuran;
                        if (! $ukuran) {
                            return '-';
                        }

                        return floatval($ukuran->panjang).' x '.floatval($ukuran->lebar).' x '.floatval($ukuran->tebal);
                    }),

                TextColumn::make('jenis_veneer')
                    ->label('Jenis Hasil')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'kering' => 'Veneer Kering',
                        'jadi' => 'Veneer Jadi',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'kering' => 'warning',
                        'jadi' => 'success',
                        default => 'gray',
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
                        $ditolak = $record->ditolak_gudang_at
                            && (! $record->diserahkan_at || $record->ditolak_gudang_at->gte($record->diserahkan_at));

                        if ($ditolak) {
                            return 'Ditolak Gudang: '.($record->alasan_tolak_gudang ?? '-');
                        }

                        if (! $record->diserahkan_at) {
                            return 'Belum Diserahkan';
                        }

                        return 'Diserahkan '.$record->diserahkan_at->translatedFormat('d M Y H:i')
                            .' oleh '.($record->diserahkanOleh?->name ?? '-');
                    })
                    ->color(function ($record) {
                        $ditolak = $record->ditolak_gudang_at
                            && (! $record->diserahkan_at || $record->ditolak_gudang_at->gte($record->diserahkan_at));

                        return match (true) {
                            $ditolak => 'danger',
                            (bool) $record->diserahkan_at => 'success',
                            default => 'warning',
                        };
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi')
                    ->using(function (array $data, string $model, $livewire): Model {
                        $idProduksi = $livewire->ownerRecord->id;
                        $idModal = $data['id_modal_pilih_veneer'];
                        $firstModel = null;

                        foreach ($data['hasil_list'] as $hasil) {
                            $record = $model::create([
                                'id_produksi_pilih_veneer' => $idProduksi,
                                'id_modal_pilih_veneer' => $idModal,
                                'jenis_veneer' => $hasil['jenis_veneer'] ?? 'jadi',
                                'kw' => $hasil['kw'],
                                'no_palet' => $hasil['no_palet'],
                                'jumlah' => $hasil['jumlah'],
                            ]);
                            if (! $firstModel) {
                                $firstModel = $record;
                            }
                        }

                        return $firstModel ?? new $model;
                    }),
            ])
            ->recordActions([
                Action::make('kirimUlangKeGudang')
                    ->label(fn ($record) => $record->ditolak_gudang_at ? 'Kirim Ulang ke Gudang' : 'Kirim ke Gudang')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->visible(function ($record) {
                        // sudah diterima gudang: tidak bisa dikirim lagi
                        if ($record->diterima_gudang_at) {
                            return false;
                        }

                        // belum pernah diserahkan
                        if (is_null($record->diserahkan_at)) {
                            return true;
                        }

                        // sedang ditolak gudang: boleh kirim ulang
                        return $record->ditolak_gudang_at
                            && $record->ditolak_gudang_at->gte($record->diserahkan_at);
                    })
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        try {
                            app(HasilPilihVeneerService::class)->serahKeGudang($record);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Diserahkan')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Berhasil Diserahkan')
                            ->body('Baris ini otomatis menunggu di halaman Gudang Veneer Jadi.')
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi')
                    ->after(function ($record, array $data) {
                        if (! empty($data['tambahan_hasil'])) {
                            foreach ($data['tambahan_hasil'] as $tambahan) {
                                HasilPilihVeneer::create([
                                    'id_produksi_pilih_veneer' => $record->id_produksi_pilih_veneer,
                                    'id_modal_pilih_veneer' => $data['id_modal_pilih_veneer'] ?? $record->id_modal_pilih_veneer,
                                    'jenis_veneer' => $tambahan['jenis_veneer'] ?? 'jadi',
                                    'kw' => $tambahan['kw'],
                                    'no_palet' => $tambahan['no_palet'],
                                    'jumlah' => $tambahan['jumlah'],
                                ]);
                            }
                        }
                    }),

                DeleteAction::make()
                    ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'),
                ]),
            ]);
    }
}
