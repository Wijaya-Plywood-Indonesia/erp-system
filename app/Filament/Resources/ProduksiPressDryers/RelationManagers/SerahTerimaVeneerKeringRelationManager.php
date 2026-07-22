<?php

namespace App\Filament\Resources\ProduksiPressDryers\RelationManagers;

use App\Models\ProduksiJoint;
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
            ProduksiRepair::class, ProduksiJoint::class => 'Terima Veneer',
            default => 'Serah Terima Veneer Kering',
        };
    }

    protected function getTipe(): string
    {
        $owner = $this->getOwnerRecord();

        // 🔧 FIX: pakai instanceof, bukan get_class() persis.
        // Kalau model di-extend / di-proxy (mis. oleh package atau
        // subclass), get_class() tidak cocok dan tipe jatuh ke 'unknown'
        // tanpa error apa pun.
        return match (true) {
            $owner instanceof ProduksiPressDryer => 'dryer',
            $owner instanceof ProduksiKedi => 'kedi',
            $owner instanceof ProduksiRepair => 'repair',
            $owner instanceof ProduksiJoint => 'joint',
            default => 'unknown',
        };
    }

    /**
     * 🔧 FIX: satu-satunya sumber kebenaran untuk ID owner.
     * Sebelumnya dipakai `->id` langsung — kalau model punya
     * $primaryKey non-standar, hasilnya NULL dan tersimpan diam-diam.
     */
    protected function getOwnerId(): int|string|null
    {
        return $this->getOwnerRecord()->getKey();
    }

    /**
     * Nama kolom FK id_produksi_* di tabel serah_terima_veneer_kering,
     * sesuai tipe owner saat ini. Hanya berlaku untuk 'repair' & 'joint'.
     */
    protected function getKolomProduksi(): ?string
    {
        return match ($this->getTipe()) {
            'repair' => 'id_produksi_repair',
            'joint' => 'id_produksi_joint',
            default => null,
        };
    }

    protected function getLabelProduksi(): string
    {
        return match ($this->getTipe()) {
            'repair' => 'Produksi REPAIR',
            'joint' => 'Produksi JOINT',
            default => '-',
        };
    }

    /**
     * 🔧 FIX: guard terpusat. Dipanggil di awal setiap action supaya
     * tidak pernah lagi ada update dengan kolom NULL / nilai NULL.
     *
     * @return array{0: string, 1: int|string}
     */
    protected function pastikanKonteksProduksi(): array
    {
        $kolom = $this->getKolomProduksi();
        $ownerId = $this->getOwnerId();

        if ($kolom === null) {
            throw new \RuntimeException(
                'Tujuan produksi tidak dikenali (tipe: '.$this->getTipe().'). '
                .'Owner: '.get_class($this->getOwnerRecord()).'.'
            );
        }

        if (empty($ownerId)) {
            throw new \RuntimeException(
                'ID produksi penerima tidak terbaca dari '
                .get_class($this->getOwnerRecord()).' '
                .'(primary key: '.$this->getOwnerRecord()->getKeyName().').'
            );
        }

        return [$kolom, $ownerId];
    }

    /**
     * 🔧 FIX: update serah terima + verifikasi FK benar-benar ikut tersimpan.
     * Kalau kolom terbuang mass-assignment atau nilainya kosong,
     * langsung throw supaya transaksi rollback dan user dapat pesan jelas —
     * bukan "sukses" tapi kolom NULL seperti kejadian di baris 98 & 99.
     */
    protected function tandaiDiterima(
        SerahTerimaVeneerKering $fresh,
        string $kolomProduksi,
        int|string $ownerId,
        string $jenisTerima,
        string $labelPenerima,
    ): void {
        $payload = [
            'diterima_oleh' => Auth::user()->name.' - '.$labelPenerima,
            'jenis_terima' => $jenisTerima,
            'status' => 'Terima Veneer',
        ];

        $payload[$kolomProduksi] = $ownerId;

        $fresh->fill($payload);

        if (! array_key_exists($kolomProduksi, $fresh->getDirty())) {
            throw new \RuntimeException(
                "Kolom {$kolomProduksi} tidak ikut tersimpan. "
                .'Periksa $fillable pada model SerahTerimaVeneerKering.'
            );
        }

        $fresh->save();

        // Sanity check terakhir: baca ulang dari DB dalam transaksi yang sama.
        $fresh->refresh();

        if (empty($fresh->{$kolomProduksi})) {
            throw new \RuntimeException(
                "Kolom {$kolomProduksi} tetap kosong setelah disimpan."
            );
        }
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Tidak dipakai untuk tipe_sumber='gudang' / 'gudang_jadi'.
     */
    protected function getPreviewData($record): array
    {
        return [
            'no_palet' => match ($record->tipe_sumber) {
                'dryer' => $record->detailHasil?->no_palet !== null
                    ? 'dry-'.$record->detailHasil->no_palet
                    : null,
                'kedi' => $record->detailBongkarKedi?->no_palet !== null
                    ? 'kedi-'.$record->detailBongkarKedi->no_palet
                    : null,
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
        $ownerId = $this->getOwnerId(); // 🔧 FIX: getKey(), bukan ->id
        $kolomProduksi = $this->getKolomProduksi();

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId, $kolomProduksi) {
                if ($tipe === 'dryer' || $tipe === 'kedi') {
                    return $query
                        ->with([
                            'detailHasil.ukuran',
                            'detailHasil.jenisKayu',
                            'detailBongkarKedi.ukuran',
                            'detailBongkarKedi.jenisKayu',
                        ])
                        ->orderBy('created_at', 'desc');
                }

                // Repair & Joint: reset constraint hasMany, tampilkan semua
                // antrean bertujuan sesuai tipe ini yang menunggu + riwayat sendiri
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $q = $query
                    ->with([
                        'detailHasil.ukuran',
                        'detailHasil.jenisKayu',
                        'detailBongkarKedi.ukuran',
                        'detailBongkarKedi.jenisKayu',
                        'mutasiKeluarPalet.mutasiKeluar.ukuran',
                        'mutasiKeluarPalet.mutasiKeluar.jenisKayu',
                        'mutasiKeluarPaletJadi.mutasiKeluar.jenisKayu',
                    ])
                    ->where(function ($qq) use ($tipe) {
                        $qq->where('tujuan', $tipe);

                        if ($tipe === 'repair') {
                            $qq->orWhere('tipe_sumber', 'gudang');
                        }
                    });

                if ($kolomProduksi && $ownerId) {
                    $q->where(function ($qq) use ($kolomProduksi, $ownerId) {
                        $qq->where('diterima_oleh', '-')
                            ->orWhere($kolomProduksi, $ownerId);
                    });
                }

                return $q
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
                        'gudang' => 'Gudang Kering',
                        'gudang_jadi' => 'Gudang Jadi',
                        default => '-',
                    })
                    ->color(fn ($state) => match ($state) {
                        'dryer' => 'info',
                        'kedi' => 'warning',
                        'gudang' => 'gray',
                        'gudang_jadi' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->getStateUsing(fn ($record) => match ($record->tipe_sumber) {
                        'dryer' => $record->detailHasil?->no_palet !== null
                            ? 'dry-'.$record->detailHasil->no_palet
                            : '-',
                        'kedi' => $record->detailBongkarKedi?->no_palet !== null
                            ? 'kd-'.$record->detailBongkarKedi->no_palet
                            : '-',
                        'gudang' => $record->mutasiKeluarPalet?->no_palet ?? '-',
                        'gudang_jadi' => $record->mutasiKeluarPaletJadi?->nomor_palet !== null
                            ? 'jd-'.$record->mutasiKeluarPaletJadi->nomor_palet
                            : '-',
                        default => '-',
                    })
                    ->badge()
                    ->color('info'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(function ($record) {
                        $mutasiJadi = $record->mutasiKeluarPaletJadi?->mutasiKeluar;

                        $ukuran = match ($record->tipe_sumber) {
                            'dryer' => $record->detailHasil?->ukuran?->nama_ukuran,
                            'kedi' => $record->detailBongkarKedi?->ukuran?->nama_ukuran,
                            'gudang' => $record->mutasiKeluarPalet?->mutasiKeluar?->ukuran?->nama_ukuran,
                            'gudang_jadi' => $mutasiJadi !== null
                                ? ($mutasiJadi->panjang + 0).'x'.($mutasiJadi->lebar + 0).'x'.($mutasiJadi->tebal + 0)
                                : null,
                            default => null,
                        } ?? '-';

                        $kodeKayu = match ($record->tipe_sumber) {
                            'dryer' => $record->detailHasil?->jenisKayu?->kode_kayu,
                            'kedi' => $record->detailBongkarKedi?->jenisKayu?->kode_kayu,
                            'gudang' => $record->mutasiKeluarPalet?->mutasiKeluar?->jenisKayu?->kode_kayu,
                            'gudang_jadi' => $mutasiJadi?->jenisKayu?->kode_kayu,
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
                        'gudang_jadi' => $record->mutasiKeluarPaletJadi?->mutasiKeluar?->kw_grade ?? '-',
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
                        'gudang_jadi' => $record->mutasiKeluarPaletJadi?->jumlah_lembar !== null
                            ? number_format((float) $record->mutasiKeluarPaletJadi->jumlah_lembar, 0)
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
                // ── Terima dari Dryer / Kedi: pakai modal (pilih Kering/Jadi) ──
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
                    ->visible(fn ($record) => $tipe === 'repair'
                        && $record->diterima_oleh === '-'
                        && ! in_array($record->tipe_sumber, ['gudang', 'gudang_jadi'], true))
                    ->action(function ($record, array $data) {
                        try {
                            [$kolomProduksi, $ownerId] = $this->pastikanKonteksProduksi();

                            DB::transaction(function () use ($record, $ownerId, $kolomProduksi, $data) {
                                $fresh = SerahTerimaVeneerKering::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Veneer ini sudah diambil produksi lain.');
                                }

                                $this->tandaiDiterima(
                                    $fresh,
                                    $kolomProduksi,
                                    $ownerId,
                                    $data['jenis_terima'],
                                    $this->getLabelProduksi(),
                                );

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

                // ── Terima dari Gudang Veneer Kering: langsung, tanpa modal ──
                Action::make('terimaGudang')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => $tipe === 'repair'
                        && $record->diterima_oleh === '-'
                        && $record->tipe_sumber === 'gudang')
                    ->action(function ($record) {
                        try {
                            [$kolomProduksi, $ownerId] = $this->pastikanKonteksProduksi();

                            DB::transaction(function () use ($record, $ownerId, $kolomProduksi) {
                                $fresh = SerahTerimaVeneerKering::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Veneer ini sudah diambil produksi lain.');
                                }

                                $this->tandaiDiterima(
                                    $fresh,
                                    $kolomProduksi,
                                    $ownerId,
                                    'kering',
                                    $this->getLabelProduksi(),
                                );

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

                // ── Terima dari Gudang Veneer JADI: langsung, tanpa modal ──
                //    Dipakai bersama oleh Repair & Joint.
                Action::make('terimaGudangJadi')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn ($record) => in_array($tipe, ['repair', 'joint'], true)
                        && $record->diterima_oleh === '-'
                        && $record->tipe_sumber === 'gudang_jadi')
                    ->action(function ($record) {
                        try {
                            [$kolomProduksi, $ownerId] = $this->pastikanKonteksProduksi();

                            DB::transaction(function () use ($record, $ownerId, $kolomProduksi) {
                                $fresh = SerahTerimaVeneerKering::with('mutasiKeluarPaletJadi.mutasiKeluar')
                                    ->lockForUpdate()
                                    ->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Veneer ini sudah diambil produksi lain.');
                                }

                                $mutasi = $fresh->mutasiKeluarPaletJadi?->mutasiKeluar;

                                if (! $mutasi) {
                                    throw new \RuntimeException('Data mutasi keluar tidak ditemukan.');
                                }

                                if (! is_null($mutasi->id_produksi_repair) || ! is_null($mutasi->id_produksi_hp)) {
                                    throw new \RuntimeException('Barang ini sudah diambil di sisi tujuan lain.');
                                }

                                $this->tandaiDiterima(
                                    $fresh,
                                    $kolomProduksi,
                                    $ownerId,
                                    'jadi',
                                    $this->getLabelProduksi(),
                                );

                                app(StokVeneerJadiService::class)->terimaKeluarGudang($fresh);
                            });

                            Notification::make()
                                ->title('Veneer Jadi Berhasil Diterima')
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
