<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\HppPlatformJadiLog;
use App\Models\HppVeneerJadiLog;
use App\Models\PlatformJadiMutasiKeluar;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\SerahTerimaMasukHp;
use App\Models\StokPlatformJadi;
use App\Models\StokVeneerJadi;
use App\Models\VeneerJadiMutasiKeluar;
use App\Models\VeneerJadiMutasiKeluarPalet;
use App\Models\HppTriplekJadiLog;
use App\Models\TriplekJadiMutasiKeluar;
use App\Models\TriplekJadiMutasiKeluarPalet;
use App\Models\StokTriplekJadi;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MutasiMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'mutasiMasuk';
    protected static ?string $title = 'Serah Terima';

    protected function getTableQuery(): Builder
    {
        return SerahTerimaMasukHp::query()
            ->where(function (Builder $q) {
                $q->whereNull('diterima_by')
                    ->orWhereDate('diterima_at', today());
            })
            ->orderByRaw('CASE WHEN diterima_by IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('tanggal_keluar');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordTitleAttribute('kw_grade')
            ->columns([
                TextColumn::make('tanggal_keluar')
                    ->label('Tanggal Masuk')
                    ->dateTime('d/m/Y H:i')
                    ->color('gray'),
                TextColumn::make('sumber')
                    ->label('Sumber')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'veneer'        => 'info',
                        'platform_jadi' => 'purple',
                        'triplek_jadi'  => 'success',
                        default         => 'gray',
                    })
                    ->formatStateUsing(fn(string $state) => match ($state) {
                        'veneer'        => 'Veneer Jadi',
                        'platform_jadi' => 'Platform Jadi',
                        'triplek_jadi'  => 'Triplek Jadi',
                        default         => $state,
                    })
                    ->searchable(),
                TextColumn::make('jenis_nama')
                    ->label('Jenis Barang')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(fn($record) => ((float) $record->panjang + 0) . 'x' . ((float) $record->lebar + 0) . 'x' . ((float) $record->tebal + 0))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $q) use ($search) {
                            $q->where('panjang', 'like', "%{$search}%")
                                ->orWhere('lebar', 'like', "%{$search}%")
                                ->orWhere('tebal', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('kw_grade')
                    ->label('KW')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('nomor_palet')
                    ->label('Nomor Palet')
                    ->alignCenter()
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('jumlah_lembar')
                    ->label('Jumlah Lembar')
                    ->formatStateUsing(fn($state) => number_format($state) . ' Lbr')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),
                TextColumn::make('kubikasi')
                    ->label('Kubikasi')
                    ->color('warning')
                    ->getStateUsing(fn(SerahTerimaMasukHp $record) => number_format($record->kubikasi(), 4))
                    ->alignRight(),
                TextColumn::make('operator.name')
                    ->label('Penyerah')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('penerima.name')
                    ->label('Penerima')
                    ->color('gray')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->wrap()
                    ->color('gray')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('sumber')
                    ->label('Sumber Material')
                    ->options([
                        'veneer'        => 'Veneer Jadi',
                        'platform_jadi' => 'Platform Jadi',
                    ])
                    ->query(function (Builder $query, array $data) {
                        logger('FILTER DATA', $data);
                        if (! empty($data['value'])) {
                            $query->where('sumber', $data['value']);
                        }
                    }),
            ])
            ->actions([
                // 🌟 Style disamakan dengan tab Sanding: link + ikon, bukan tombol
                // solid penuh. Logic di dalam ->action() TIDAK diubah sama sekali.
                Action::make('terima_material')
                    ->label('Terima')
                    ->link()
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(SerahTerimaMasukHp $record) => is_null($record->diterima_by))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Material')
                    ->modalDescription('Apakah Anda yakin barang sudah dihitung fisik dan sesuai dengan dokumen? Tindakan ini akan mendaftarkan palet ini ke antrean produksi Hotpress dan memotong stok gudang asal.')
                    ->action(function (SerahTerimaMasukHp $record) {
                        logger('TERIMA CLICKED', ['id' => $record->id, 'id_asli' => $record->id_asli, 'sumber' => $record->sumber]);
                        $produksiHpId = $this->getOwnerRecord()->id;
                        $userId       = Auth::id();
                        $userName     = Auth::user()?->name ?? 'System';

                        try {
                            DB::transaction(function () use ($record, $produksiHpId, $userId, $userName) {

                                if ($record->sumber === 'veneer') {
                                    $palet = VeneerJadiMutasiKeluarPalet::lockForUpdate()->findOrFail($record->id_asli);
                                    $mk    = VeneerJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                                    if (! is_null($palet->diterima_by)) {
                                        throw new \Exception('Palet ini sudah pernah diterima sebelumnya.');
                                    }

                                    $qty = (int) $palet->jumlah_lembar;

                                    $stok = StokVeneerJadi::where('id_jenis_kayu', $mk->id_jenis_kayu)
                                        ->where('panjang', $mk->panjang)->where('lebar', $mk->lebar)
                                        ->where('tebal', $mk->tebal)->where('kw_grade', $mk->kw_grade)
                                        ->lockForUpdate()->first();

                                    if (! $stok) {
                                        throw new \Exception('Stok Veneer Jadi sumber tidak ditemukan.');
                                    }
                                    if ($qty > (int) $stok->stok_lembar) {
                                        throw new \Exception('Stok gudang asal tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                                    }

                                    $kubikasiPalet = ($mk->panjang * $mk->lebar * $mk->tebal * $qty) / 10000000;
                                    $nilaiPalet    = round($qty * (float) $stok->hpp_average, 2);

                                    $before = [
                                        'lembar'   => (int) $stok->stok_lembar,
                                        'kubikasi' => (float) $stok->stok_kubikasi,
                                        'nilai'    => (float) $stok->nilai_stok,
                                    ];
                                    $after = [
                                        'lembar'   => $before['lembar'] - $qty,
                                        'kubikasi' => max(0.0, round($before['kubikasi'] - $kubikasiPalet, 6)),
                                        'nilai'    => max(0.0, round($before['nilai'] - $nilaiPalet, 2)),
                                    ];

                                    $log = HppVeneerJadiLog::create([
                                        'id_jenis_kayu' => $mk->id_jenis_kayu,
                                        'panjang' => $mk->panjang,
                                        'lebar' => $mk->lebar,
                                        'tebal' => $mk->tebal,
                                        'kw_grade' => $mk->kw_grade,
                                        'tanggal' => now(),
                                        'tipe_transaksi' => 'KELUAR',
                                        'referensi_type' => VeneerJadiMutasiKeluarPalet::class,
                                        'referensi_id' => $palet->id,
                                        'total_lembar' => $qty,
                                        'total_kubikasi' => $kubikasiPalet,
                                        'hpp_pekerja' => $stok->hpp_pekerja_last ?? 0,
                                        'hpp_bahan_penolong' => $stok->hpp_bahan_penolong_last ?? 0,
                                        'hpp_average' => $stok->hpp_average,
                                        'nilai_stok' => $nilaiPalet,
                                        'stok_lembar_before' => $before['lembar'],
                                        'stok_kubikasi_before' => $before['kubikasi'],
                                        'nilai_stok_before' => $before['nilai'],
                                        'stok_lembar_after' => $after['lembar'],
                                        'stok_kubikasi_after' => $after['kubikasi'],
                                        'nilai_stok_after' => $after['nilai'],
                                        'keterangan' => "Diterima di Hotpress — Palet #{$palet->nomor_palet} tujuan [{$mk->tujuan}] oleh {$userName}",
                                    ]);

                                    $stok->update([
                                        'stok_lembar' => $after['lembar'],
                                        'stok_kubikasi' => $after['kubikasi'],
                                        'nilai_stok' => $after['nilai'],
                                        'id_last_log' => $log->id,
                                    ]);

                                    $kubikasiAsli = ($mk->panjang * $mk->lebar * $mk->tebal * $palet->jumlah_lembar) / 10000000;
                                    $palet->update([
                                        'diterima_by' => $userId,
                                        'diterima_at' => now(),
                                        'tebal' => $mk->tebal,
                                        'stok_kubikasi' => $kubikasiAsli,
                                    ]);
                                    $mk->update(['id_produksi_hp' => $produksiHpId]);
                                } elseif ($record->sumber === 'triplek_jadi') {
                                    // 🌟 TRIPLEK JADI: kembali ke hotpress untuk perbaikan.
                                    // Hanya potong stok triplek jadi + tulis log KELUAR, per palet.
                                    // TIDAK menambah stok apa pun (barang sedang diperbaiki).
                                    $palet = TriplekJadiMutasiKeluarPalet::lockForUpdate()->findOrFail($record->id_asli);
                                    $mk    = TriplekJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                                    if (! is_null($palet->diterima_by)) {
                                        throw new \Exception('Palet ini sudah pernah diterima sebelumnya.');
                                    }

                                    $qty = (int) $palet->jumlah_lembar;

                                    $stok = StokTriplekJadi::where('id_jenis_kayu', $mk->id_jenis_kayu)
                                        ->where('panjang', $mk->panjang)->where('lebar', $mk->lebar)
                                        ->where('tebal', $mk->tebal)->where('kw_grade', $mk->kw_grade)
                                        ->lockForUpdate()->first();

                                    if (! $stok) {
                                        throw new \Exception('Stok Triplek Jadi sumber tidak ditemukan.');
                                    }
                                    if ($qty > (int) $stok->stok_lembar) {
                                        throw new \Exception('Stok gudang asal tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                                    }

                                    $kubikasiPalet = ($mk->panjang * $mk->lebar * $mk->tebal * $qty) / 10000000;
                                    $nilaiPalet    = round($qty * (float) $stok->hpp_average, 2);

                                    $before = [
                                        'lembar'   => (int) $stok->stok_lembar,
                                        'kubikasi' => (float) $stok->stok_kubikasi,
                                        'nilai'    => (float) $stok->nilai_stok,
                                    ];
                                    $after = [
                                        'lembar'   => $before['lembar'] - $qty,
                                        'kubikasi' => max(0.0, round($before['kubikasi'] - $kubikasiPalet, 6)),
                                        'nilai'    => max(0.0, round($before['nilai'] - $nilaiPalet, 2)),
                                    ];

                                    $log = HppTriplekJadiLog::create([
                                        'id_jenis_kayu' => $mk->id_jenis_kayu,
                                        'panjang' => $mk->panjang,
                                        'lebar' => $mk->lebar,
                                        'tebal' => $mk->tebal,
                                        'kw_grade' => $mk->kw_grade,
                                        'tanggal' => now(),
                                        'tipe_transaksi' => 'keluar',
                                        'referensi_type' => TriplekJadiMutasiKeluarPalet::class,
                                        'referensi_id' => $palet->id,
                                        'total_lembar' => $qty,
                                        'total_kubikasi' => $kubikasiPalet,
                                        'hpp_pekerja' => 0,
                                        'hpp_bahan_penolong' => 0,
                                        'hpp_average' => $stok->hpp_average,
                                        'nilai_stok' => $nilaiPalet,
                                        'stok_lembar_before' => $before['lembar'],
                                        'stok_kubikasi_before' => $before['kubikasi'],
                                        'nilai_stok_before' => $before['nilai'],
                                        'stok_lembar_after' => $after['lembar'],
                                        'stok_kubikasi_after' => $after['kubikasi'],
                                        'nilai_stok_after' => $after['nilai'],
                                        'keterangan' => "Diterima di Hotpress (perbaikan) — Palet #{$palet->nomor_palet} oleh {$userName}",
                                    ]);

                                    $stok->update([
                                        'stok_lembar' => $after['lembar'],
                                        'stok_kubikasi' => $after['kubikasi'],
                                        'nilai_stok' => $after['nilai'],
                                        'id_last_log' => $log->id,
                                    ]);

                                    $palet->update(['diterima_by' => $userId, 'diterima_at' => now()]);
                                    $mk->update(['id_produksi_hp' => $produksiHpId]);
                                } else {
                                    // platform_jadi (logika lama)
                                    $palet = PlatformJadiMutasiKeluarPalet::lockForUpdate()->findOrFail($record->id_asli);
                                    $mk    = PlatformJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                                    if (! is_null($palet->diterima_by)) {
                                        throw new \Exception('Palet ini sudah pernah diterima sebelumnya.');
                                    }

                                    $qty = (int) $palet->jumlah_lembar;

                                    $stok = StokPlatformJadi::where('id_jenis_barang', $mk->id_jenis_barang)
                                        ->where('panjang', $mk->panjang)->where('lebar', $mk->lebar)
                                        ->where('tebal', $mk->tebal)->where('kw_grade', $mk->kw_grade)
                                        ->lockForUpdate()->first();

                                    if (! $stok) {
                                        throw new \Exception('Stok Platform Jadi sumber tidak ditemukan.');
                                    }
                                    if ($qty > (int) $stok->stok_lembar) {
                                        throw new \Exception('Stok gudang asal tidak mencukupi. Tersedia: ' . $stok->stok_lembar . ' lembar.');
                                    }

                                    $kubikasiPalet = ($mk->panjang * $mk->lebar * $mk->tebal * $qty) / 10000000;
                                    $nilaiPalet    = round($qty * (float) $stok->hpp_average, 2);

                                    $before = [
                                        'lembar'   => (int) $stok->stok_lembar,
                                        'kubikasi' => (float) $stok->stok_kubikasi,
                                        'nilai'    => (float) $stok->nilai_stok,
                                    ];
                                    $after = [
                                        'lembar'   => $before['lembar'] - $qty,
                                        'kubikasi' => max(0.0, round($before['kubikasi'] - $kubikasiPalet, 6)),
                                        'nilai'    => max(0.0, round($before['nilai'] - $nilaiPalet, 2)),
                                    ];

                                    $log = HppPlatformJadiLog::create([
                                        'id_jenis_barang' => $mk->id_jenis_barang,
                                        'panjang' => $mk->panjang,
                                        'lebar' => $mk->lebar,
                                        'tebal' => $mk->tebal,
                                        'kw_grade' => $mk->kw_grade,
                                        'tanggal' => now(),
                                        'tipe_transaksi' => 'keluar',
                                        'referensi_type' => PlatformJadiMutasiKeluarPalet::class,
                                        'referensi_id' => $palet->id,
                                        'total_lembar' => $qty,
                                        'total_kubikasi' => $kubikasiPalet,
                                        'hpp_pekerja' => 0,
                                        'hpp_bahan_penolong' => 0,
                                        'hpp_average' => (float) $stok->hpp_average,
                                        'nilai_stok' => $nilaiPalet,
                                        'stok_lembar_before' => $before['lembar'],
                                        'stok_kubikasi_before' => $before['kubikasi'],
                                        'nilai_stok_before' => $before['nilai'],
                                        'stok_lembar_after' => $after['lembar'],
                                        'stok_kubikasi_after' => $after['kubikasi'],
                                        'nilai_stok_after' => $after['nilai'],
                                        'keterangan' => "Diterima di Hotpress — Palet #{$palet->nomor_palet} tujuan [{$mk->tujuan}] oleh {$userName}",
                                    ]);

                                    $stok->update([
                                        'stok_lembar' => $after['lembar'],
                                        'stok_kubikasi' => $after['kubikasi'],
                                        'nilai_stok' => $after['nilai'],
                                        'id_last_log' => $log->id,
                                    ]);

                                    $palet->update(['diterima_by' => $userId, 'diterima_at' => now()]);
                                    $mk->update(['id_produksi_hp' => $produksiHpId]);
                                }
                            });

                            Notification::make()->success()->title('Material Berhasil Diterima')->send();
                        } catch (\Throwable $e) {
                            logger()->error('TERIMA GAGAL', [
                                'id_asli' => $record->id_asli,
                                'sumber' => $record->sumber,
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            Notification::make()->danger()->title('Gagal Menerima Material')->body($e->getMessage())->send();
                        }
                    }),

                // 🌟 Action baru: TOLAK
                //
                // Data berasal dari VIEW `serah_terima_masuk_hp` yang tidak bisa
                // di-UPDATE langsung, jadi update dilakukan ke tabel palet asli
                // sesuai `sumber` (veneer/platform_jadi/triplek_jadi). Karena
                // stok baru dipotong saat action `terima_material` dijalankan,
                // dan `tolak_material` hanya boleh muncul saat `diterima_by`
                // masih null, maka action ini TIDAK PERNAH memanggil service
                // stok/HPP apa pun — cukup menandai palet asli, dan baris ini
                // otomatis hilang dari VIEW pada refresh berikutnya karena
                // filter `p.ditolak_by IS NULL` di masing-masing SELECT.
                //
                // Style disamakan dengan tab Sanding: link + ikon.
                Action::make('tolak_material')
                    ->label('Tolak')
                    ->link()
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(SerahTerimaMasukHp $record) => is_null($record->diterima_by))
                    ->requiresConfirmation()
                    ->modalHeading('Tolak Material Ini?')
                    ->modalDescription('Palet akan hilang dari daftar Serah Terima dan TIDAK memotong / menambah stok apa pun.')
                    ->schema([
                        Textarea::make('alasan_tolak')
                            ->label('Alasan Penolakan')
                            ->placeholder('Contoh: jumlah lembar tidak sesuai fisik / salah kirim dari gudang')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (SerahTerimaMasukHp $record, array $data) {
                        logger('TOLAK CLICKED', ['id' => $record->id, 'id_asli' => $record->id_asli, 'sumber' => $record->sumber]);
                        $userId = Auth::id();

                        try {
                            DB::transaction(function () use ($record, $data, $userId) {
                                $paletModel = match ($record->sumber) {
                                    'veneer' => VeneerJadiMutasiKeluarPalet::class,
                                    'triplek_jadi' => TriplekJadiMutasiKeluarPalet::class,
                                    'platform_jadi' => PlatformJadiMutasiKeluarPalet::class,
                                    default => throw new \Exception('Sumber material tidak dikenali.'),
                                };

                                $palet = $paletModel::lockForUpdate()->findOrFail($record->id_asli);

                                if (! is_null($palet->diterima_by)) {
                                    throw new \Exception('Palet ini sudah pernah diterima, tidak bisa ditolak.');
                                }

                                if (! is_null($palet->ditolak_by)) {
                                    throw new \Exception('Palet ini sudah pernah ditolak sebelumnya.');
                                }

                                $palet->update([
                                    'ditolak_by' => $userId,
                                    'alasan_tolak' => $data['alasan_tolak'],
                                    'ditolak_at' => now(),
                                ]);
                            });

                            Notification::make()
                                ->warning()
                                ->title('Material Ditolak')
                                ->body('Palet tidak akan muncul lagi di daftar Serah Terima dan stok gudang asal tidak berubah.')
                                ->send();
                        } catch (\Throwable $e) {
                            logger()->error('TOLAK GAGAL', [
                                'id_asli' => $record->id_asli,
                                'sumber' => $record->sumber,
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            Notification::make()->danger()->title('Gagal Menolak Material')->body($e->getMessage())->send();
                        }
                    }),

                Action::make('done_label')
                    ->label('✓ SELESAI')
                    ->color('success')
                    ->disabled()
                    ->visible(fn(SerahTerimaMasukHp $record) => ! is_null($record->diterima_by)),
            ]);
    }
}
