<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\JenisKayu;
use App\Models\PlatformHasilHp;
use App\Models\ProduksiGrajitriplek;
use App\Models\ProduksiHp;
use App\Models\ProduksiSanding;
use App\Models\SerahTerimaHp;
use App\Models\TriplekHasilHp;
use App\Services\StokPlatformMthService;
use App\Services\StokTriplekMthService;
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

    /**
     * Mapping dari `tipe` (tab yang sedang dibuka) ke value kolom `tujuan`.
     * 'hp' tidak dipetakan karena tab HP adalah sumber, bukan tujuan.
     */
    private const TIPE_TO_TUJUAN = [
        'graji' => 'graji_triplek',
        'sanding' => 'sanding',
    ];

    protected static string $relationship = 'serahTerimaHp';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return match (get_class($ownerRecord)) {
            ProduksiHp::class => 'Serah Hasil Produksi',
            ProduksiGrajitriplek::class => 'Terima Triplek',
            ProduksiSanding::class => 'Terima Platform/Plywood',
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
     * Bekerja untuk semua sumber (triplek HP, platform HP, hasil Graji, hasil Sanding)
     * lewat accessor `hasil`, `barangSetengahJadi`, dan `jumlah` di model SerahTerimaHp.
     */
    protected function getPreviewData($record): array
    {
        $hasil = $record->hasil;
        $bsj = $record->barangSetengahJadi;

        return [
            'no_palet' => $hasil?->no_palet ?? '-',
            'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $bsj?->grade?->nama_grade ?? '-',
            'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
            'isi' => $record->jumlah ?? '-',
            'dari_mesin' => $hasil?->mesin?->nama_mesin ?? '-',
            'asal' => $record->asalLabel,
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
            'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
            'hasilGrajiTriplek.barangSetengahJadiHp.grade',
            'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
            'hasilSanding.mesin',
            'hasilSanding.barangSetengahJadi.jenisBarang',
            'hasilSanding.barangSetengahJadi.grade',
            'hasilSanding.barangSetengahJadi.ukuran',
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
                    // Menuju Graji Triplek: cukup filter langsung dari kolom `tujuan`
                    // (mencakup dari hotpress via id_triplek_hasil_hp ATAU serah manual dari Sanding).
                    return $query
                        ->where('tujuan', self::TIPE_TO_TUJUAN['graji'])
                        ->where(function ($q) use ($ownerId) {
                            $q->where('diterima_oleh', '-')
                                ->orWhere('id_produksi_graji_triplek', $ownerId);
                        })
                        ->orderBy('diterima_oleh', 'asc')
                        ->orderBy('created_at', 'desc');
                }

                if ($tipe === 'sanding') {
                    // Menuju Sanding: cukup filter langsung dari kolom `tujuan`
                    // (mencakup dari hotpress via id_platform_hasil_hp ATAU serah manual dari Graji Triplek).
                    return $query
                        ->where('tujuan', self::TIPE_TO_TUJUAN['sanding'])
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

                TextColumn::make('asal_label')
                    ->label('Asal')
                    ->state(fn ($record) => $record->asalLabel)
                    ->badge()
                    ->color(fn ($record) => match ($record->asalLabel) {
                        'Hotpress' => 'info',
                        'Graji Triplek' => 'warning',
                        'Sanding' => 'purple',
                        default => 'gray',
                    }),

                TextColumn::make('mesin')
                    ->label('Mesin')
                    ->state(fn ($record) => $record->hasil?->mesin?->nama_mesin ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $record->barangSetengahJadi?->grade?->nama_grade ?? '-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->barangSetengahJadi?->ukuran?->nama_ukuran ?? '-'),

                TextColumn::make('isi')
                    ->label('Jumlah Lembar')
                    ->state(fn ($record) => $record->jumlah ?? '-')
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
                        'Terima Triplek', 'Terima Platform' => 'success',
                        'Serah Triplek', 'Serah Platform', 'Serah ke Sanding', 'Serah ke Graji' => 'warning',
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

                                    Placeholder::make('preview_asal')
                                        ->label('Asal')
                                        ->content($preview['asal']),

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
                    // Muncul kalau tujuannya sesuai dengan tab yang sedang dibuka, dan belum diterima.
                    ->visible(function ($record) use ($tipe) {
                        if ($record->diterima_oleh !== '-') {
                            return false;
                        }

                        return $record->tujuan === (self::TIPE_TO_TUJUAN[$tipe] ?? null);
                    })
                    ->action(function ($record) use ($ownerId, $tipe) {
                        $stokTriplekService = app(StokTriplekMthService::class);
                        $stokPlatformService = app(StokPlatformMthService::class);

                        try {
                            DB::transaction(function () use ($record, $ownerId, $tipe, $stokTriplekService, $stokPlatformService) {
                                $fresh = SerahTerimaHp::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain.');
                                }

                                if ($tipe === 'graji') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Graji Triplek',
                                        'id_produksi_graji_triplek' => $ownerId,
                                        'status' => $fresh->id_triplek_hasil_hp ? 'Terima Triplek' : 'Terima dari Sanding',
                                    ]);

                                    // Stok triplek BERTAMBAH hanya kalau barang berasal dari hotpress.
                                    // Serah manual dari Sanding -> Graji tidak mengubah stok (sementara).
                                    if ($fresh->id_triplek_hasil_hp) {
                                        $this->prosesTerimaTriplek($fresh, $stokTriplekService);
                                    }

                                    return;
                                }

                                if ($tipe === 'sanding') {
                                    $fresh->update([
                                        'diterima_oleh' => Auth::user()->name.' - Sanding',
                                        'id_produksi_sanding' => $ownerId,
                                        'status' => $fresh->id_platform_hasil_hp ? 'Terima Platform' : 'Terima dari Graji',
                                    ]);

                                    // Stok platform BERTAMBAH hanya kalau barang berasal dari hotpress.
                                    // Serah manual dari Graji -> Sanding tidak mengubah stok (sementara).
                                    if ($fresh->id_platform_hasil_hp) {
                                        $this->prosesTerimaPlatform($fresh, $stokPlatformService);
                                    }
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
     * Resolve data dari hasil triplek HP, lalu delegasikan penambahan stok
     * ke StokTriplekMthService. HPP belum dihitung (0 dulu, menyusul).
     */
    protected function prosesTerimaTriplek(SerahTerimaHp $serahTerima, StokTriplekMthService $service): void
    {
        $hasil = $serahTerima->triplekHasilHp()
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

        // "Jenis Barang" pada hasil triplek sebenarnya merepresentasikan jenis kayu,
        // tapi disimpan lewat tabel jenis_barang (bukan jenis_kayus) — dicocokkan by nama.
        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $lembar = (float) $hasil->isi;
        $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

        $service->tambah(
            idJenisKayu: $jenisKayu->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembar,
            kubikasi: $kubikasi,
            keterangan: 'Masuk dari Graji — terima triplek dari hotpress (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }

    /**
     * Resolve data dari hasil platform HP, lalu delegasikan penambahan stok
     * ke StokPlatformMthService. HPP belum dihitung (0 dulu, menyusul).
     */
    protected function prosesTerimaPlatform(SerahTerimaHp $serahTerima, StokPlatformMthService $service): void
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

        $lembar = (float) $hasil->isi;
        $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

        $service->tambah(
            idJenisKayu: $jenisKayu->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembar,
            kubikasi: $kubikasi,
            keterangan: 'Masuk dari Sanding — terima platform dari hotpress (via serah terima #'.$serahTerima->id.')',
            referensi: $serahTerima,
        );
    }
}
