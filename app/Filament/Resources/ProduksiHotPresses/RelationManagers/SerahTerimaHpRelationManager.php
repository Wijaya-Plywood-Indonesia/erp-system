<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\HppPlatformMthLog;
use App\Models\JenisKayu;
use App\Models\PlatformHasilHp;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiSanding;
use App\Models\SerahTerimaHp;
use App\Models\StokPlatformMth;
use App\Models\TriplekHasilHp;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaHpRelationManager extends RelationManager
{
    private const ROLE_ADMIN = ['super_admin', 'Super Admin', 'admin_kayu'];

    protected static string $relationship = 'serahTerimaHp';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiHp::class => 'Serah Hasil Produksi',
            ProduksiGrajitriplek::class => 'Terima Triplek',
            ProduksiSanding::class => 'Terima Platform',
            default => 'Serah Terima',
        };
    }

    protected function getTipe(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiHp::class => 'hp',
            ProduksiGrajitriplek::class => 'graji',
            ProduksiSanding::class => 'sanding',
            default => 'unknown',
        };
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Bekerja untuk sumber triplek maupun platform lewat accessor `hasil`.
     */
    protected function getPreviewData($record): array
    {
        $hasil = $record->hasil;

        return [
            'no_palet' => $hasil?->no_palet ?? '-',
            'jenis_barang' => $hasil?->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $hasil?->barangSetengahJadi?->grade?->nama_grade ?? '-',
            'ukuran' => $hasil?->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-',
            'isi' => $hasil?->isi ?? '-',
            'dari_mesin' => $hasil?->mesin?->nama_mesin ?? '-',
        ];
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipe();
        $ownerId = $this->getOwnerRecord()->id;

        $eagerLoads = [
            'triplekHasilHp.mesin',
            'triplekHasilHp.barangSetengahJadi.jenisBarang',
            'triplekHasilHp.barangSetengahJadi.grade',
            'triplekHasilHp.barangSetengahJadi.ukuran',
            'platformHasilHp.mesin',
            'platformHasilHp.barangSetengahJadi.jenisBarang',
            'platformHasilHp.barangSetengahJadi.grade',
            'platformHasilHp.barangSetengahJadi.ukuran',
        ];

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe, $ownerId, $eagerLoads) {
                // Reset constraint bawaan dari relasi dasar (tanpa mengganti objek $query,
                // karena Filament butuh instance query yang sama untuk proses selanjutnya)
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $query->with($eagerLoads);

                if ($tipe === 'hp') {
                    $triplekIds = TriplekHasilHp::where('id_produksi_hp', $ownerId)->pluck('id');
                    $platformIds = PlatformHasilHp::where('id_produksi_hp', $ownerId)->pluck('id');

                    return $query
                        ->where(function ($q) use ($triplekIds, $platformIds) {
                            $q->whereIn('id_triplek_hasil_hp', $triplekIds)
                                ->orWhereIn('id_platform_hasil_hp', $platformIds);
                        })
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'graji') {
                    return $query
                        ->whereNotNull('id_triplek_hasil_hp')
                        ->where(function ($q) use ($ownerId) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere('id_produksi_graji_triplek', $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'sanding') {
                    return $query
                        ->whereNotNull('id_platform_hasil_hp')
                        ->where(function ($q) use ($ownerId) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere('id_produksi_sanding', $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                return $query;
            })
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->state(fn ($record) => $record->hasil?->no_palet ?? '-')
                    ->badge()
                    ->color('info'),

                TextColumn::make('tipe_sumber')
                    ->label('Jenis')
                    ->badge()
                    ->state(fn ($record) => $record->tipeSumber === 'platform' ? 'Platform' : 'Triplek')
                    ->color(fn ($record) => $record->tipeSumber === 'platform' ? 'purple' : 'info'),

                TextColumn::make('mesin')
                    ->label('Mesin')
                    ->state(fn ($record) => $record->hasil?->mesin?->nama_mesin ?? '-')->toggleable(isToggledHiddenByDefault : true),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $record->hasil?->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $record->hasil?->barangSetengahJadi?->grade?->nama_grade ?? '-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->hasil?->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-'),

                TextColumn::make('isi')
                    ->label('Jumlah Lembar')
                    ->state(fn ($record) => $record->hasil?->isi ?? '-')
                    ->alignCenter(),

                // Kolom "Jenis" (Platform/Triplek) — tampil default khusus di tab
                // Serah (hp), karena di sana bisa campur dua sumber sekaligus.
                // Di tab Graji/Sanding disembunyikan default karena sumbernya
                // sudah pasti satu jenis saja.

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
                        'Terima Triplek', 'Terima Platform' => 'success',
                        'Serah Triplek', 'Serah Platform' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima barang ini?')
                    ->modalDescription('Periksa data berikut sebelum menerima.')
                    ->schema(function ($record) {
                        $preview = $this->getPreviewData($record);

                        return [
                            Grid::make(2)
                                ->schema([
                                    Placeholder::make('preview_no_palet')
                                        ->label('No. Palet')
                                        ->content($preview['no_palet']),

                                    Placeholder::make('preview_jenis_barang')
                                        ->label('Jenis Barang')
                                        ->content($preview['jenis_barang']),

                                    Placeholder::make('preview_grade')
                                        ->label('Grade')
                                        ->content($preview['grade']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_isi')
                                        ->label('Jumlah Lembar')
                                        ->content($preview['isi']),

                                    Placeholder::make('preview_dari_mesin')
                                        ->label('Dari Mesin')
                                        ->content($preview['dari_mesin']),
                                ]),
                        ];
                    })
                    // Muncul kalau dibuka dari Graji (khusus sumber triplek)
                    // atau dari Sanding (khusus sumber platform), dan belum diterima
                    ->visible(function ($record) use ($tipe) {
                        if ($record->diterima_oleh !== '-') {
                            return false;
                        }

                        return ($tipe === 'graji' && $record->id_triplek_hasil_hp !== null)
                            || ($tipe === 'sanding' && $record->id_platform_hasil_hp !== null);
                    })
                    ->action(function ($record) use ($ownerId, $tipe) {
                        try {
                            DB::transaction(function () use ($record, $ownerId, $tipe) {
                                $fresh = SerahTerimaHp::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain.');
                                }

                                if ($tipe === 'graji') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Graji Triplek',
                                        'id_produksi_graji_triplek' => $ownerId,
                                        'status' => 'Terima Triplek',
                                    ]);

                                    // Stok triplek belum diupdate di sini — menyusul
                                    return;
                                }

                                if ($tipe === 'sanding') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Sanding',
                                        'id_produksi_sanding' => $ownerId,
                                        'status' => 'Terima Platform',
                                    ]);

                                    $this->tambahStokPlatform($fresh);
                                }
                            });

                            Notification::make()
                                ->title('Barang Berhasil Diterima')
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

    /**
     * Tambah stok platform mentah + catat log HPP saat sanding menerima
     * hasil platform dari hotpress. HPP belum dihitung (0 dulu, menyusul).
     */
    protected function tambahStokPlatform(SerahTerimaHp $serahTerima): void
    {
        $hasil = $serahTerima->platformHasilHp()
            ->with('barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang')
            ->first();

        if (! $hasil || ! $hasil->barangSetengahJadi) {
            throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
        }

        $ukuran = $hasil->barangSetengahJadi->ukuran;
        $grade = $hasil->barangSetengahJadi->grade;
        $jenisBarang = $hasil->barangSetengahJadi->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
        }

        // "Jenis Barang" pada hasil platform sebenarnya merepresentasikan jenis kayu,
        // tapi disimpan lewat tabel jenis_barang (bukan jenis_kayus) — dicocokkan by nama.
        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $idJenisKayu = $jenisKayu->id;

        $lembar = (float) $hasil->isi;
        $kubikasi = $lembar * (float) $ukuran->kubikasi;

        $stok = StokPlatformMth::firstOrCreate(
            [
                'id_jenis_kayu' => $idJenisKayu,
                'panjang' => $ukuran->panjang,
                'lebar' => $ukuran->lebar,
                'tebal' => $ukuran->tebal,
                'kw_grade' => $grade->nama_grade,
            ],
            [
                'stok_lembar' => 0,
                'stok_kubikasi' => 0,
                'nilai_stok' => 0,
                'hpp_average' => 0,
                'hpp_pekerja_last' => 0,
                'hpp_bahan_penolong_last' => 0,
            ]
        );

        $stokLembarBefore = $stok->stok_lembar;
        $stokKubikasiBefore = $stok->stok_kubikasi;
        $nilaiStokBefore = $stok->nilai_stok;

        $stok->stok_lembar += $lembar;
        $stok->stok_kubikasi += $kubikasi;
        $stok->save();

        $log = HppPlatformMthLog::create([
            'id_jenis_kayu' => $idJenisKayu,
            'panjang' => $ukuran->panjang,
            'lebar' => $ukuran->lebar,
            'tebal' => $ukuran->tebal,
            'kw_grade' => $grade->nama_grade,
            'tanggal' => now()->toDateString(),
            'tipe_transaksi' => 'Masuk dari Sanding',
            'keterangan' => 'Terima platform dari hotpress (via serah terima #'.$serahTerima->id.')',
            'referensi_type' => SerahTerimaHp::class,
            'referensi_id' => $serahTerima->id,
            'total_lembar' => $lembar,
            'total_kubikasi' => $kubikasi,
            'hpp_pekerja' => 0,
            'hpp_bahan_penolong' => 0,
            'hpp_average' => $stok->hpp_average,
            'nilai_stok' => $stok->nilai_stok,
            'stok_lembar_before' => $stokLembarBefore,
            'stok_kubikasi_before' => $stokKubikasiBefore,
            'nilai_stok_before' => $nilaiStokBefore,
            'stok_lembar_after' => $stok->stok_lembar,
            'stok_kubikasi_after' => $stok->stok_kubikasi,
            'nilai_stok_after' => $stok->nilai_stok,
        ]);

        $stok->update(['id_last_log' => $log->id]);
    }
}
