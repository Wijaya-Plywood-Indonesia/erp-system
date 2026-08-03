<?php

namespace App\Filament\Resources\HasilSandings\Tables;

use App\Models\ModalSanding;
use App\Models\SerahTerimaTriplekJadi; // Pastikan Model ini di-import
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;

class HasilSandingsTable
{
    /**
     * Apakah palet hasil sanding ini SUDAH DITERIMA di gudang tujuan?
     *
     * "Diterima" letaknya di tabel berbeda tergantung tujuan serah:
     *  - triplek_jadi  -> SerahTerimaTriplekJadi (diterima_oleh != '-')
     *  - platform_jadi -> tabel serah terima platform (ISI SAAT SIAP — lihat TODO)
     *
     * Selama belum diserah (diserahkan_at null) pasti belum diterima -> false,
     * sehingga Edit/Delete tetap tampil untuk barang yang masih menunggu diserah.
     */
    protected static function sudahDiterima($record): bool
    {
        // Belum diserah sama sekali -> jelas belum diterima.
        if ($record->diserahkan_at === null) {
            return false;
        }

        // Tujuan: Gudang Triplek Jadi
        if ($record->tujuan_serah === 'triplek_jadi') {
            return SerahTerimaTriplekJadi::where('id_hasil_sanding', $record->id)
                ->where('diterima_oleh', '!=', '-')
                ->exists();
        }

        // Tujuan: Gudang Platform Jadi
        if ($record->tujuan_serah === 'platform_jadi') {
            // Beda pola dengan triplek: di platform, baris SerahTerimaPlatformJadi
            // BARU dibuat saat barang diterima (bukan saat serah). Jadi tidak ada
            // penanda '-' — cukup cek keberadaan barisnya.
            return \App\Models\SerahTerimaPlatformJadi::where('id_hasil_sanding', $record->id)
                ->exists();
        }

        return false;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadi.grade.kategoriBarang',
                'barangSetengahJadi.ukuran',
                'barangSetengahJadi.jenisBarang',
                'modalSandings.barangSetengahJadi.grade.kategoriBarang',
                'modalSandings.barangSetengahJadi.ukuran',
                'modalSandings.barangSetengahJadi.jenisBarang',
                'modalSandings.serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'modalSandings.serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
                'modalSandings.serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
            ]))
            ->columns([
                TextColumn::make('barangSetengahJadiInfo')
                    ->label('Barang Setengah Jadi')
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

                        // 2. Jika null, ambil dari ModalSanding (via matching no_palet & id_produksi_sanding)
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

                            // Cek jika dari serahTerimaHp (Triplek Jadi, Platform Mentah, Triplek Mentah)
                            $serah = $modal->serahTerimaHp;
                            if ($serah) {
                                if ($serah->id_triplek_mutasi_keluar !== null) {
                                    $m = $serah->triplekMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0) . ' x ' . ($m->lebar + 0) . ' x ' . ($m->tebal + 0);
                                        return "Plywood — {$ukuran} - "
                                            . ($m->jenisKayu?->nama_kayu ?? '-') . ' - '
                                            . ($m->kw_grade ?? '-');
                                    }
                                    return 'Triplek Jadi — -';
                                }

                                if ($serah->id_platform_mth_mutasi_keluar !== null) {
                                    $m = $serah->platformMthMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0) . ' x ' . ($m->lebar + 0) . ' x ' . ($m->tebal + 0);
                                        return "Platform — {$ukuran} - "
                                            . ($m->jenisKayu?->nama_kayu ?? '-') . ' - '
                                            . ($m->kw_grade ?? '-');
                                    }
                                    return 'Platform Mentah — -';
                                }

                                if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                                    $m = $serah->triplekMthMutasiKeluar;
                                    if ($m) {
                                        $ukuran = ($m->panjang + 0) . ' x ' . ($m->lebar + 0) . ' x ' . ($m->tebal + 0);
                                        return "Plywood — {$ukuran} - "
                                            . ($m->jenisKayu?->nama_kayu ?? '-') . ' - '
                                            . ($m->kw_grade ?? '-');
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

                //--- INI BUAT FILTER AJA
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
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                Action::make('serahkan')
                    ->label('Serahkan')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->diserahkan_at === null)
                    ->schema(function ($record) {
                        $record->loadMissing(['barangSetengahJadi.ukuran', 'barangSetengahJadi.jenisBarang', 'barangSetengahJadi.grade']);

                        $bsj    = $record->barangSetengahJadi;
                        $ukuran = $bsj?->ukuran;

                        $dimensi = $ukuran
                            ? ($ukuran->panjang + 0) . ' × ' . ($ukuran->lebar + 0) . ' × ' . ($ukuran->tebal + 0)
                            : '-';

                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('info_barang')
                                    ->label('Barang')
                                    ->content($bsj?->jenisBarang?->nama_jenis_barang ?? '-'),
                                Placeholder::make('info_grade')
                                    ->label('Grade')
                                    ->content($bsj?->grade?->nama_grade ?? '-'),
                                Placeholder::make('info_ukuran')
                                    ->label('Ukuran (P × L × T)')
                                    ->content($dimensi),
                                Placeholder::make('info_palet')
                                    ->label('No. Palet')
                                    ->content((string) ($record->no_palet ?? '-')),
                                Placeholder::make('info_qty')
                                    ->label('Kuantitas')
                                    ->content(new HtmlString('<strong>' . number_format((float) $record->kuantitas) . ' lembar</strong>')),
                                Placeholder::make('info_status')
                                    ->label('Status Sanding')
                                    ->content((string) ($record->status ?? '-')),
                            ]),

                            Radio::make('tujuan_serah')
                                ->label('Serahkan ke')
                                ->options([
                                    'platform_jadi' => 'Gudang Platform Jadi',
                                    'triplek_jadi'  => 'Gudang Triplek Jadi',
                                ])
                                ->default('platform_jadi')
                                ->required(),
                        ];
                    })
                    ->modalHeading(fn ($record) => 'Serahkan Hasil Sanding — Palet ' . $record->no_palet)
                    ->modalSubmitActionLabel('Serahkan')
                    ->modalWidth('md')
                    ->requiresConfirmation(false)
                    ->action(function ($record, array $data) {
                        // 1. Ambil ulang dari DB (hindari race condition)
                        $fresh = $record->fresh(['barangSetengahJadi.ukuran']);

                        if ($fresh->diserahkan_at !== null) {
                            Notification::make()->warning()
                                ->title('Palet ini sudah diserahkan')
                                ->body('Diserahkan ke ' . $fresh->label_tujuan_serah . '. Muat ulang halaman.')
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

                        // 3. Data barang & ukuran harus lengkap
                        if (! $fresh->barangSetengahJadi || ! $fresh->barangSetengahJadi->ukuran) {
                            Notification::make()->danger()
                                ->title('Data barang tidak lengkap')
                                ->body('Barang setengah jadi / ukuran tidak ditemukan.')
                                ->send();
                            return;
                        }

                        if (! in_array($data['tujuan_serah'] ?? null, ['platform_jadi', 'triplek_jadi'], true)) {
                            Notification::make()->danger()->title('Tujuan serah tidak valid.')->send();
                            return;
                        }

                        // ── SIMPAN PERUBAHAN DI HASIL SANDING ────────
                        $fresh->update([
                            'tujuan_serah'    => $data['tujuan_serah'],
                            'diserahkan_oleh' => auth()->id(),
                            'diserahkan_at'   => now(),
                        ]);

                        // ── INSERT KE TABEL SERAH TERIMA TRIPLEK JADI ──
                        if ($data['tujuan_serah'] === 'triplek_jadi') {
                            SerahTerimaTriplekJadi::create([
                                'id_hasil_sanding' => $fresh->id,
                                'diserahkan_oleh'  => auth()->user()->name, // pakai nama sesuai tipe string
                                'diterima_oleh'    => '-',
                                'status'           => 'Serah Sanding',
                            ]);
                        }
                        // *Jika butuh insert ke SerahTerimaPlatform, tambahkan else if disini*

                        Notification::make()->success()
                            ->title('Palet ' . $fresh->no_palet . ' diserahkan')
                            ->body(number_format((float) $fresh->kuantitas) . ' lembar → '
                                . ($data['tujuan_serah'] === 'platform_jadi' ? 'Gudang Platform Jadi' : 'Gudang Triplek Jadi'))
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(
                        fn($livewire, $record) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        || self::sudahDiterima($record)
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire, $record) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        || self::sudahDiterima($record)
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}