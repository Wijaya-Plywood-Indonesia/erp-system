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
use Filament\Actions\Action;
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
                    ->color(fn(string $state) => $state === 'veneer' ? 'info' : 'purple')
                    ->formatStateUsing(fn(string $state) => $state === 'veneer' ? 'Veneer Jadi' : 'Platform Jadi')
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
                Action::make('terima_material')
                    ->label('TERIMA')
                    ->button()
                    ->color('warning')
                    ->visible(fn(SerahTerimaMasukHp $record) => is_null($record->diterima_by))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penerimaan Material')
                    ->modalDescription('Apakah Anda yakin barang sudah dihitung fisik dan sesuai dengan dokumen? Tindakan ini akan langsung mendaftarkan palet ke antrean produksi Hotpress DAN memotong stok gudang asal.')
                    ->action(function (SerahTerimaMasukHp $record) {
                        $produksiHpId = $this->getOwnerRecord()->id;
                        $userId       = Auth::id();
                        $userName     = Auth::user()?->name ?? 'System';

                        DB::transaction(function () use ($record, $produksiHpId, $userId, $userName) {
                            if ($record->sumber === 'veneer') {
                                $palet = VeneerJadiMutasiKeluarPalet::findOrFail($record->id_asli);
                                $mk    = VeneerJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                                $qty = (int) $palet->jumlah_lembar;

                                $stok = StokVeneerJadi::where('id_jenis_kayu', $mk->id_jenis_kayu)
                                    ->where('panjang', $mk->panjang)
                                    ->where('lebar', $mk->lebar)
                                    ->where('tebal', $mk->tebal)
                                    ->where('kw_grade', $mk->kw_grade)
                                    ->lockForUpdate()
                                    ->first();

                                if (! $stok) {
                                    throw new \Exception('Stok Veneer Jadi sumber tidak ditemukan. Periksa data mutasi keluar.');
                                }

                                if ($qty > (int) $stok->stok_lembar) {
                                    throw new \Exception('Stok gudang asal tidak mencukupi untuk konfirmasi penerimaan ini. Tersedia: ' . $stok->stok_lembar . ' lembar.');
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
                                    'id_jenis_kayu'        => $mk->id_jenis_kayu,
                                    'panjang'              => $mk->panjang,
                                    'lebar'                => $mk->lebar,
                                    'tebal'                => $mk->tebal,
                                    'kw_grade'             => $mk->kw_grade,
                                    'tanggal'              => now(),
                                    'tipe_transaksi'       => 'KELUAR',
                                    'referensi_type'       => VeneerJadiMutasiKeluarPalet::class,
                                    'referensi_id'         => $palet->id,
                                    'total_lembar'         => $qty,
                                    'total_kubikasi'       => $kubikasiPalet,
                                    'hpp_pekerja'          => $stok->hpp_pekerja_last ?? 0,
                                    'hpp_bahan_penolong'   => $stok->hpp_bahan_penolong_last ?? 0,
                                    'hpp_average'          => $stok->hpp_average,
                                    'nilai_stok'           => $nilaiPalet,
                                    'stok_lembar_before'   => $before['lembar'],
                                    'stok_kubikasi_before' => $before['kubikasi'],
                                    'nilai_stok_before'    => $before['nilai'],
                                    'stok_lembar_after'    => $after['lembar'],
                                    'stok_kubikasi_after'  => $after['kubikasi'],
                                    'nilai_stok_after'     => $after['nilai'],
                                    'keterangan'           => "Diterima di Hotpress — Palet #{$palet->nomor_palet} tujuan [{$mk->tujuan}] oleh {$userName}",
                                ]);

                                $stok->update([
                                    'stok_lembar'   => $after['lembar'],
                                    'stok_kubikasi' => $after['kubikasi'],
                                    'nilai_stok'    => $after['nilai'],
                                    'id_last_log'   => $log->id,
                                ]);

                                $kubikasiAsli = ($mk->panjang * $mk->lebar * $mk->tebal * $palet->jumlah_lembar) / 10000000;

                                $palet->update([
                                    'diterima_by'   => $userId,
                                    'diterima_at'   => now(),
                                    'tebal'         => $mk->tebal,
                                    'stok_kubikasi' => $kubikasiAsli,
                                ]);
                                $mk->update([
                                    'diterima_by'    => $userId,
                                    'diterima_at'    => now(),
                                    'id_produksi_hp' => $produksiHpId,
                                ]);
                            } else {
                                $palet = PlatformJadiMutasiKeluarPalet::findOrFail($record->id_asli);
                                $mk    = PlatformJadiMutasiKeluar::findOrFail($record->id_mutasi_keluar);

                                $qty = (int) $palet->jumlah_lembar;

                                $stok = StokPlatformJadi::where('id_jenis_barang', $mk->id_jenis_barang)
                                    ->where('panjang', $mk->panjang)
                                    ->where('lebar', $mk->lebar)
                                    ->where('tebal', $mk->tebal)
                                    ->where('kw_grade', $mk->kw_grade)
                                    ->lockForUpdate()
                                    ->first();

                                if (! $stok) {
                                    throw new \Exception('Stok Platform Jadi sumber tidak ditemukan. Periksa data mutasi keluar.');
                                }

                                if ($qty > (int) $stok->stok_lembar) {
                                    throw new \Exception('Stok gudang asal tidak mencukupi untuk konfirmasi penerimaan ini. Tersedia: ' . $stok->stok_lembar . ' lembar.');
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
                                    'id_jenis_barang'      => $mk->id_jenis_barang,
                                    'panjang'              => $mk->panjang,
                                    'lebar'                => $mk->lebar,
                                    'tebal'                => $mk->tebal,
                                    'kw_grade'             => $mk->kw_grade,
                                    'tanggal'              => now(),
                                    'tipe_transaksi'       => 'keluar',
                                    'referensi_type'       => PlatformJadiMutasiKeluarPalet::class,
                                    'referensi_id'         => $palet->id,
                                    'total_lembar'         => $qty,
                                    'total_kubikasi'       => $kubikasiPalet,
                                    'hpp_pekerja'          => 0,
                                    'hpp_bahan_penolong'   => 0,
                                    'hpp_average'          => (float) $stok->hpp_average,
                                    'nilai_stok'           => $nilaiPalet,
                                    'stok_lembar_before'   => $before['lembar'],
                                    'stok_kubikasi_before' => $before['kubikasi'],
                                    'nilai_stok_before'    => $before['nilai'],
                                    'stok_lembar_after'    => $after['lembar'],
                                    'stok_kubikasi_after'  => $after['kubikasi'],
                                    'nilai_stok_after'     => $after['nilai'],
                                    'keterangan'           => "Diterima di Hotpress — Palet #{$palet->nomor_palet} tujuan [{$mk->tujuan}] oleh {$userName}",
                                ]);

                                $stok->update([
                                    'stok_lembar'   => $after['lembar'],
                                    'stok_kubikasi' => $after['kubikasi'],
                                    'nilai_stok'    => $after['nilai'],
                                    'id_last_log'   => $log->id,
                                ]);

                                $mk->update([
                                    'diterima_by'    => $userId,
                                    'diterima_at'    => now(),
                                    'id_produksi_hp' => $produksiHpId,
                                ]);
                            }
                        });

                        Notification::make()->success()->title('Material Berhasil Diterima')->send();
                    }),

                Action::make('done_label')
                    ->label('✓ SELESAI')
                    ->color('success')
                    ->disabled()
                    ->visible(fn(SerahTerimaMasukHp $record) => ! is_null($record->diterima_by)),
            ]);
    }
}
