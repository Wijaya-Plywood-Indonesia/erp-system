<?php

namespace App\Filament\Resources\HasilSandings\Tables;

use App\Models\SerahTerimaPlatformJadi;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\Ukuran;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class HasilSandingsTable
{
    /**
     * Apakah palet hasil sanding ini SUDAH DITERIMA di gudang tujuan?
     *
     *  - triplek_jadi  -> SerahTerimaTriplekJadi (diterima_oleh != '-')
     *  - platform_jadi -> SerahTerimaPlatformJadi (baris dibuat saat diterima)
     */
    protected static function sudahDiterima($record): bool
    {
        if ($record->diserahkan_at === null) {
            return false;
        }

        if ($record->tujuan_serah === 'triplek_jadi') {
            return SerahTerimaTriplekJadi::where('id_hasil_sanding', $record->id)
                ->where('diterima_oleh', '!=', '-')
                ->exists();
        }

        if ($record->tujuan_serah === 'platform_jadi') {
            return SerahTerimaPlatformJadi::where('id_hasil_sanding', $record->id)
                ->exists();
        }

        return false;
    }

    /**
     * BarangSetengahJadi dari HasilSanding, fallback ke ModalSanding.
     */
    protected static function resolveBsj($record)
    {
        return $record->barangSetengahJadi
            ?? $record->modalSanding?->barangSetengahJadi;
    }

    /**
     * Data mutasi (triplek jadi / platform mentah / triplek mentah),
     * dari serahTerimaHp di record ini, atau fallback via ModalSanding.
     */
    protected static function resolveMutasi($record)
    {
        $serah = $record->serahTerimaHp ?? $record->modalSanding?->serahTerimaHp;

        return $serah?->triplekMutasiKeluar
            ?? $serah?->platformMthMutasiKeluar
            ?? $serah?->triplekMthMutasiKeluar;
    }

    /**
     * Dimensi dari mutasi (jika ada).
     */
    protected static function dimensiDariMutasi($record): ?array
    {
        $m = self::resolveMutasi($record);

        if (! $m) {
            return null;
        }

        return [
            'panjang' => $m->panjang + 0,
            'lebar' => $m->lebar + 0,
            'tebal' => $m->tebal + 0,
        ];
    }

    /**
     * Pastikan ukuran tersedia. Jika belum ada, buat Ukuran baru dari
     * dimensi mutasi dan tautkan ke BSJ (bila BSJ ada).
     */
    protected static function pastikanUkuran($record): bool
    {
        $bsj = self::resolveBsj($record);

        if ($bsj && $bsj->ukuran) {
            return true;
        }

        $dim = self::dimensiDariMutasi($record);
        if (! $dim) {
            return false;
        }

        $ukuran = Ukuran::firstOrCreate($dim);

        if ($bsj) {
            $bsj->update(['id_ukuran' => $ukuran->id]);
        }

        return true;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadi.grade.kategoriBarang',
                'barangSetengahJadi.ukuran',
                'barangSetengahJadi.jenisBarang',
                'serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
                'serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
                'modalSandings.barangSetengahJadi.grade.kategoriBarang',
                'modalSandings.barangSetengahJadi.ukuran',
                'modalSandings.barangSetengahJadi.jenisBarang',
                'modalSandings.serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'modalSandings.serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
                'modalSandings.serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('barangSetengahJadiInfo')
                    ->label('Modal')
                    ->getStateUsing(function ($record) {
                        // 1. Coba dari HasilSanding langsung (sumber lama / hotpress / graji)
                        $bsj = $record->barangSetengahJadi;
                        if ($bsj) {
                            $kategori = $bsj->grade?->kategoriBarang?->nama_kategori ?? '-';
                            $ukuran = $bsj->ukuran?->dimensi ?? '-';
                            $grade = $bsj->grade?->nama_grade ?? '-';
                            $jenis = $bsj->jenisBarang?->nama_jenis_barang ?? '-';

                            return "{$kategori} — {$ukuran} - {$jenis} - {$grade}";
                        }

                        // 2. Cek jika dari serahTerimaHp di record ini langsung
                        $serah = $record->serahTerimaHp;
                        if ($serah) {
                            if ($serah->id_triplek_mutasi_keluar !== null) {
                                $m = $serah->triplekMutasiKeluar;
                                if ($m) {
                                    $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                    return "Plywood — {$ukuran} - "
                                        .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                        .($m->kw_grade ?? '-');
                                }

                                return 'Triplek Jadi — -';
                            }

                            if ($serah->id_platform_mth_mutasi_keluar !== null) {
                                $m = $serah->platformMthMutasiKeluar;
                                if ($m) {
                                    $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                    return "Platform — {$ukuran} - "
                                        .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                        .($m->kw_grade ?? '-');
                                }

                                return 'Platform Mentah — -';
                            }

                            if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                                $m = $serah->triplekMthMutasiKeluar;
                                if ($m) {
                                    $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                    return "Plywood — {$ukuran} - "
                                        .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                        .($m->kw_grade ?? '-');
                                }

                                return 'Triplek Mentah — -';
                            }
                        }

                        // 3. Fallback: ambil dari ModalSanding
                        $modal = $record->modalSanding;
                        if ($modal) {
                            $bsj = $modal->barangSetengahJadi;
                            if ($bsj) {
                                $kategori = $bsj->grade?->kategoriBarang?->nama_kategori ?? '-';
                                $ukuran = $bsj->ukuran?->dimensi ?? '-';
                                $grade = $bsj->grade?->nama_grade ?? '-';
                                $jenis = $bsj->jenisBarang?->nama_jenis_barang ?? '-';

                                return "{$kategori} — {$ukuran} - {$jenis} - {$grade}";
                            }

                            $serah = $modal->serahTerimaHp;
                            if ($serah) {
                                if ($serah->id_triplek_mutasi_keluar !== null) {
                                    $m = $serah->triplekMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                        return "Plywood — {$ukuran} - "
                                            .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                            .($m->kw_grade ?? '-');
                                    }

                                    return 'Triplek Jadi — -';
                                }

                                if ($serah->id_platform_mth_mutasi_keluar !== null) {
                                    $m = $serah->platformMthMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                        return "Platform — {$ukuran} - "
                                            .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                            .($m->kw_grade ?? '-');
                                    }

                                    return 'Platform Mentah — -';
                                }

                                if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                                    $m = $serah->triplekMthMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0);

                                        return "Plywood — {$ukuran} - "
                                            .($m->jenisKayu?->nama_kayu ?? '-').' - '
                                            .($m->kw_grade ?? '-');
                                    }

                                    return 'Triplek Mentah — -';
                                }
                            }
                        }

                        return '-';
                    }),

                TextColumn::make('kuantitas')
                    ->label('Qty')
                    ->sortable(),

                TextColumn::make('jumlah_sanding_face')
                    ->label('Face'),

                TextColumn::make('jumlah_sanding_back')
                    ->label('Back'),

                TextColumn::make('no_palet')
                    ->label('Palet')
                    ->sortable(),

                TextColumn::make('status')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // --- INI BUAT FILTER AJA
                TextColumn::make('barangSetengahJadi.grade.kategoriBarang.nama_kategori')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.ukuran.dimensi')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('label_tujuan_serah')
                    ->label('Diserahkan Ke')
                    ->badge()
                    ->color(fn ($record) => $record->tujuan_serah ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serahkan')
                    ->label('Serahkan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->diserahkan_at === null)
                    ->schema(function ($record) {
                        $record->loadMissing([
                            'barangSetengahJadi.ukuran',
                            'barangSetengahJadi.jenisBarang',
                            'barangSetengahJadi.grade',
                            'modalSandings.barangSetengahJadi.ukuran',
                            'modalSandings.barangSetengahJadi.jenisBarang',
                            'modalSandings.barangSetengahJadi.grade',
                        ]);

                        $bsj = self::resolveBsj($record);
                        $mutasi = self::resolveMutasi($record);
                        $ukuran = $bsj?->ukuran;

                        if ($ukuran) {
                            $dimensi = ($ukuran->panjang + 0).' × '.($ukuran->lebar + 0).' × '.($ukuran->tebal + 0);
                        } elseif ($mutasi) {
                            $dimensi = ($mutasi->panjang + 0).' × '.($mutasi->lebar + 0).' × '.($mutasi->tebal + 0);
                        } else {
                            $dimensi = '-';
                        }

                        $namaBarang = $bsj?->jenisBarang?->nama_jenis_barang
                            ?? $mutasi?->jenisKayu?->nama_kayu
                            ?? '-';

                        $namaGrade = $bsj?->grade?->nama_grade
                            ?? $mutasi?->kw_grade
                            ?? '-';

                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('info_barang')
                                    ->label('Barang')
                                    ->content($namaBarang),
                                Placeholder::make('info_grade')
                                    ->label('Grade')
                                    ->content($namaGrade),
                                Placeholder::make('info_ukuran')
                                    ->label('Ukuran (P × L × T)')
                                    ->content($dimensi),
                                Placeholder::make('info_palet')
                                    ->label('No. Palet')
                                    ->content((string) ($record->no_palet ?? '-')),
                                Placeholder::make('info_qty')
                                    ->label('Kuantitas')
                                    ->content(new HtmlString('<strong>'.number_format((float) $record->kuantitas).' lembar</strong>')),
                                Placeholder::make('info_status')
                                    ->label('Status Sanding')
                                    ->content((string) ($record->status ?? '-')),
                            ]),

                            Radio::make('tujuan_serah')
                                ->label('Serahkan ke')
                                ->options([
                                    'platform_jadi' => 'Gudang Platform Jadi',
                                    'triplek_jadi' => 'Gudang Triplek Jadi',
                                ])
                                ->default('platform_jadi')
                                ->required(),
                        ];
                    })
                    ->modalHeading(fn ($record) => 'Serahkan Hasil Sanding — Palet '.$record->no_palet)
                    ->modalSubmitActionLabel('Serahkan')
                    ->modalWidth('md')
                    ->requiresConfirmation(false)
                    ->action(function ($record, array $data) {
                        // 1. Ambil ulang dari DB (hindari race condition)
                        $fresh = $record->fresh([
                            'barangSetengahJadi.ukuran',
                            'modalSandings.barangSetengahJadi.ukuran',
                        ]);

                        if ($fresh->diserahkan_at !== null) {
                            Notification::make()->warning()
                                ->title('Palet ini sudah diserahkan')
                                ->body('Diserahkan ke '.$fresh->label_tujuan_serah.'. Muat ulang halaman.')
                                ->send();

                            return;
                        }

                        // 2. Kuantitas harus valid
                        if ((float) $fresh->kuantitas <= 0) {
                            Notification::make()->danger()
                                ->title('Kuantitas tidak valid')
                                ->body('Kuantitas palet ini 0. Perbaiki data hasil sanding.')
                                ->send();

                            return;
                        }

                        // 3. Tujuan serah harus valid
                        if (! in_array($data['tujuan_serah'] ?? null, ['platform_jadi', 'triplek_jadi'], true)) {
                            Notification::make()->danger()->title('Tujuan serah tidak valid.')->send();

                            return;
                        }

                        // 4. Satu transaksi: pastikan ukuran (buat baru jika perlu),
                        //    update hasil sanding, insert serah terima.
                        $berhasil = DB::transaction(function () use ($fresh, $data) {
                            if (! self::pastikanUkuran($fresh)) {
                                return false;
                            }

                            $fresh->update([
                                'tujuan_serah' => $data['tujuan_serah'],
                                'diserahkan_oleh' => auth()->id(),
                                'diserahkan_at' => now(),
                            ]);

                            if ($data['tujuan_serah'] === 'triplek_jadi') {
                                SerahTerimaTriplekJadi::create([
                                    'id_hasil_sanding' => $fresh->id,
                                    'diserahkan_oleh' => auth()->user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Serah Sanding',
                                ]);
                            }
                            // Jika butuh insert ke SerahTerimaPlatform, tambahkan di sini.

                            return true;
                        });

                        if (! $berhasil) {
                            Notification::make()->danger()
                                ->title('Data barang tidak lengkap')
                                ->body('Ukuran tidak ditemukan dan tidak ada sumber dimensi untuk membuatnya.')
                                ->send();

                            return;
                        }

                        Notification::make()->success()
                            ->title('Palet '.$fresh->no_palet.' diserahkan')
                            ->body(number_format((float) $fresh->kuantitas).' lembar → '
                                .($data['tujuan_serah'] === 'platform_jadi' ? 'Gudang Platform Jadi' : 'Gudang Triplek Jadi'))
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(
                        fn ($livewire, $record) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        || self::sudahDiterima($record)
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire, $record) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        || self::sudahDiterima($record)
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
