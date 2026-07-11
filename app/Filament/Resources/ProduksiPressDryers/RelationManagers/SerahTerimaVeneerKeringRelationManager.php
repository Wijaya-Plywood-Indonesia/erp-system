<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\ProduksiKedi;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRepair;
use App\Models\SerahTerimaVeneerKering;
use App\Services\StokVeneerJadiService;
use App\Services\StokVeneerKeringService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaVeneerKeringRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static string $relationship = 'serahTerimaVeneerKering';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiPressDryer::class, ProduksiKedi::class => 'Serah Veneer',
            ProduksiRepair::class => 'Terima Veneer',
            default => 'Serah Terima Veneer Kering',
        };
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiPressDryer::class => 'dryer',
            ProduksiKedi::class => 'kedi',
            ProduksiRepair::class => 'repair',
            default => 'unknown',
        };
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Tidak dipakai untuk tipe_sumber='gudang' (yang diterima langsung tanpa modal).
     */
    protected function getPreviewData($record): array
    {
        return [
            'no_palet' => match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->no_palet,
                'kedi' => $record->detailBongkarKedi?->no_palet,
                default => null,
            } ?? '-',

            'ukuran' => match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->ukuran?->nama_ukuran,
                'kedi' => $record->detailBongkarKedi?->ukuran?->nama_ukuran,
                default => null,
            } ?? '-',

            'kode_kayu' => strtoupper(match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->jenisKayu?->kode_kayu,
                'kedi' => $record->detailBongkarKedi?->jenisKayu?->kode_kayu,
                default => null,
            } ?? '-'),

            'kw' => match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->kw,
                'kedi' => $record->detailBongkarKedi?->kw,
                default => null,
            } ?? '-',

            'isi' => match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->isi,
                'kedi' => $record->detailBongkarKedi?->jumlah,
                default => null,
            } ?? '-',

            'dari_mesin' => match ($record->tipe_sumber) {
                'dryer' => 'Press Dryer',
                'kedi' => 'Kedi',
                default => '-',
            },
        ];
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId) {
                if ($tipe === 'dryer' || $tipe === 'kedi') {
                    // Dryer & Kedi: hanya tampilkan riwayat miliknya sendiri
                    // (hasManyThrough sudah filter otomatis by FK owner)
                    return $query
                        ->with([
                            'detailHasil.ukuran',
                            'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran',
                            'detailBongkarKedi.jenisKayu',
                        ])
                        ->orderBy('created_at', 'desc');
                }

                // Repair: reset constraint hasMany, tampilkan semua menunggu + riwayat sendiri
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query
                    ->with([
                        'detailHasil.ukuran',
                        'detailHasil.jenisKayu',
                        'detailBongkarKedi.ukuran',
                        'detailBongkarKedi.jenisKayu',
                        'mutasiKeluarPalet.mutasiKeluar.ukuran',
                        'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                    ])
                    // 🔒 SEMENTARA DINONAKTIFKAN: tab Repair hanya menampilkan antrean dari
                    // Gudang saja (tipe_sumber = 'gudang'). Antrean dari Dryer/Kedi
                    // langsung (tipe_sumber 'dryer'/'kedi') disembunyikan dulu atas
                    // permintaan — filter ini bisa dihapus lagi kapan saja untuk
                    // mengaktifkan kembali tampilan gabungan seperti semula.
                    ->where('tipe_sumber', 'gudang')
                    ->where(function ($q) use ($ownerId) {
                        $q->where('diterima_oleh', '-')
                            ->orWhere('id_produksi_repair', $ownerId);
                    })
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');

            })
            ->columns([
                TextColumn::make('tipe_sumber')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'dryer' => 'Press Dryer',
                        'kedi' => 'Kedi',
                        'gudang' => 'Gudang',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'dryer' => 'info',
                        'kedi' => 'warning',
                        'gudang' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->no_palet ?? '-',
                        'kedi' => $record->detailBongkarKedi?->no_palet ?? '-',
                        'gudang' => $record->mutasiKeluarPalet?->no_palet ?? '-',
                        default => '-',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(function ($record) {
                        $ukuran = match ($record->tipe_sumber) {
                            'dryer' => $record->detailHasil?->ukuran?->nama_ukuran,
                            'kedi' => $record->detailBongkarKedi?->ukuran?->nama_ukuran,
                            'gudang' => $record->mutasiKeluarPalet?->mutasiKeluar?->ukuran?->nama_ukuran,
                            default => null,
                        } ?? '-';

                        $kodeKayu = match ($record->tipe_sumber) {
                            'dryer' => $record->detailHasil?->jenisKayu?->kode_kayu,
                            'kedi' => $record->detailBongkarKedi?->jenisKayu?->kode_kayu,
                            'gudang' => $record->mutasiKeluarPalet?->mutasiKeluar?->jenisKayu?->kode_kayu,
                            default => null,
                        };

                        $kodeKayu = $kodeKayu ? strtoupper($kodeKayu) : '-';

                        return "{$ukuran} | {$kodeKayu}";
                    }),

                TextColumn::make('kw')
                    ->label('KW')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->kw ?? '-',
                        'kedi' => $record->detailBongkarKedi?->kw ?? '-',
                        'gudang' => $record->mutasiKeluarPalet?->mutasiKeluar?->kw ?? '-',
                        default => '-',
                    })
                    ->alignCenter(),

                TextColumn::make('isi')
                    ->label('Isi / Jumlah')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->isi ?? '-',
                        'kedi' => $record->detailBongkarKedi?->jumlah ?? '-',
                        'gudang' => $record->mutasiKeluarPalet?->qty !== null
                            ? number_format((float) $record->mutasiKeluarPalet->qty, 0)
                            : '-',
                        default => '-',
                    })
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('diterima_oleh')
                    ->label('Diterima Oleh')
                    ->badge()
                    ->color(fn ($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === '-' ? 'Menunggu' : $state),

                TextColumn::make('status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state) => match ($state) {
                        'Terima Veneer' => 'success',
                        'Serah Veneer' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('jenis_terima')
                    ->label('Diterima Sebagai')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'kering' => 'Veneer Kering',
                        'jadi' => 'Veneer Jadi',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'kering' => 'info',
                        'jadi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->actions([
                // ── Terima dari Dryer / Kedi: TETAP pakai modal (pilih Kering/Jadi) ──
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Veneer Kering ini?')
                    ->modalDescription('Periksa data veneer berikut, lalu pilih jenis penerimaan. Pilihan ini akan menentukan pengaruhnya ke stok veneer.')
                    ->schema(function ($record) {
                        $preview = $this->getPreviewData($record);

                        return [
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('preview_no_palet')
                                        ->label('No. Palet')
                                        ->content($preview['no_palet']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_jenis_kayu')
                                        ->label('Kode Kayu')
                                        ->content($preview['kode_kayu']),

                                    Placeholder::make('preview_kw')
                                        ->label('KW')
                                        ->content($preview['kw']),

                                    Placeholder::make('preview_isi')
                                        ->label('Isi / Jumlah')
                                        ->content($preview['isi']),

                                    Placeholder::make('preview_dari_mesin')
                                        ->label('Dari Mesin')
                                        ->content($preview['dari_mesin']),
                                ]),

                            Radio::make('jenis_terima')
                                ->label('Terima Sebagai')
                                ->options([
                                    'kering' => 'Veneer Kering',
                                    'jadi' => 'Veneer Jadi',
                                ])
                                ->descriptions([
                                    'kering' => 'Masuk ke stok Veneer Kering.',
                                    'jadi' => 'Masuk ke stok Veneer Jadi.',
                                ])
                                ->default('kering')
                                ->required()
                                ->inline(),
                        ];
                    })
                    // Hanya muncul kalau dibuka dari Repair, belum diterima, DAN bukan dari gudang
                    ->visible(fn ($record) => $tipe === 'repair' && $record->diterima_oleh === '-' && $record->tipe_sumber !== 'gudang')
                    ->action(function ($record, array $data) use ($ownerId) {
                        try {
                            DB::transaction(function () use ($record, $ownerId, $data) {
                                $fresh = SerahTerimaVeneerKering::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Veneer ini sudah diambil produksi lain.');
                                }

                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name.' - Produksi REPAIR',
                                    'id_produksi_repair' => $ownerId,
                                    'jenis_terima' => $data['jenis_terima'],
                                    'status' => 'Terima Veneer',
                                ]);

                                if ($data['jenis_terima'] === 'kering') {
                                    app(StokVeneerKeringService::class)->terimaRepair($fresh);
                                } else {
                                    app(StokVeneerJadiService::class)->terimaRepair($fresh);
                                }
                            });

                            Notification::make()
                                ->title('Veneer Kering Berhasil Diterima')
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

                // ── Terima dari Gudang Veneer Kering: LANGSUNG eksekusi, TANPA modal.
                //    Barang ini sudah pasti "kering" (memang berasal dari stok
                //    Gudang Veneer Kering), jadi tidak perlu pilihan Kering/Jadi.
                //    Titik inilah stok betul-betul berkurang & tercatat di Log
                //    (lihat StokVeneerKeringService::terimaKeluarGudang()).
                Action::make('terimaGudang')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $tipe === 'repair' && $record->diterima_oleh === '-' && $record->tipe_sumber === 'gudang')
                    ->action(function ($record) use ($ownerId) {
                        try {
                            DB::transaction(function () use ($record, $ownerId) {
                                $fresh = SerahTerimaVeneerKering::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Veneer ini sudah diambil produksi lain.');
                                }

                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name.' - Produksi REPAIR',
                                    'id_produksi_repair' => $ownerId,
                                    'jenis_terima' => 'kering',
                                    'status' => 'Terima Veneer',
                                ]);

                                app(StokVeneerKeringService::class)->terimaKeluarGudang($fresh);
                            });

                            Notification::make()
                                ->title('Veneer Kering Berhasil Diterima')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()->hasAnyRole(self::ROLE_ADMIN)),
                ]),
            ]);
    }
}
