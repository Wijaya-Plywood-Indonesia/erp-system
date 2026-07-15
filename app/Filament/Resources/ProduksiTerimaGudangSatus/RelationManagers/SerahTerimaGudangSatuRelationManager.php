<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Models\JenisKayu;
use App\Models\ProduksiNyusup;
use App\Models\SerahTerimaGudangSatu;
use App\Services\StokGudangSatuService;
use App\Services\TerimaTriplekJadiService;
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

class SerahTerimaGudangSatuRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerima';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return $ownerRecord instanceof ProduksiNyusup
            ? 'Terima Barang untuk Nyusup'
            : 'Terima Barang dari Pilih Plywood';
    }

    /**
     * Menentukan apakah relation manager ini sedang dipakai di konteks Nyusup.
     */
    protected function isNyusupContext(): bool
    {
        return $this->getOwnerRecord() instanceof ProduksiNyusup;
    }

    /**
     * Nama kolom FK yang dipakai untuk "lengket"-kan record ke owner saat ini,
     * tergantung konteks resource mana yang memanggil relation manager ini.
     */
    protected function ownerForeignKey(): string
    {
        return $this->isNyusupContext()
            ? 'id_produksi_nyusup'
            : 'id_produksi_terima_gudang_satu';
    }

    /**
     * Apakah record ini berasal dari Gudang Triplek Jadi (bukan Pilih Plywood)?
     */
    protected function isDariTriplek($record): bool
    {
        return $record->id_triplek_mutasi_keluar !== null;
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Mendukung dua asal barang: Pilih Plywood (lama) dan Triplek Jadi (baru).
     */
    protected function getPreviewData($record): array
    {
        // ── Asal: Gudang Triplek Jadi ──
        if ($this->isDariTriplek($record)) {
            $mutasi = $record->triplekMutasiKeluar;

            return [
                'jenis_barang'  => $mutasi?->jenisKayu?->nama_kayu ?? '-',
                'grade'         => $mutasi?->kw_grade ?? '-',
                'ukuran'        => $mutasi
                    ? ($mutasi->panjang + 0) . '×' . ($mutasi->lebar + 0) . '×' . ($mutasi->tebal + 0)
                    : '-',
                'kondisi'       => 'Triplek Jadi',
                'jenis_cacat'   => '-',
                'jumlah'        => $record->jumlah ?? '-',
                'dari_produksi' => 'Gudang Triplek Jadi (Mutasi #' . $record->id_triplek_mutasi_keluar . ')',
            ];
        }

        // ── Asal: Pilih Plywood (logika asli, tidak diubah) ──
        $hasil = $record->hasilPilihPlywood;
        $bsj = $record->barang_setengah_jadi;

        return [
            'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
            'grade' => $bsj?->grade?->nama_grade ?? '-',
            'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
            'kondisi' => $hasil?->kondisi ?? '-',
            'jenis_cacat' => $hasil?->jenis_cacat ?? '-',
            'jumlah' => $record->jumlah ?? '-',
            'dari_produksi' => $hasil?->produksiPilihPlywood?->tanggal_produksi ?? '-',
        ];
    }

    /**
     * Tambahkan stok plywood siap jual ketika barang PILIH PLYWOOD diterima
     * di konteks Gudang Satu (tidak berlaku untuk konteks Nyusup, dan tidak
     * dipakai untuk barang asal Triplek Jadi — itu ditangani service sendiri).
     */
    protected function tambahStokPlywoodSiapJual(SerahTerimaGudangSatu $record): void
    {
        $record->loadMissing([
            'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
            'hasilPilihPlywood.barangSetengahJadiHp.grade',
            'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
        ]);

        $bsj = $record->barang_setengah_jadi;
        $ukuran = $bsj?->ukuran;
        $grade = $bsj?->grade;
        $jenisBarang = $bsj?->jenisBarang;

        if (! $bsj || ! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap. Stok tidak dapat ditambahkan.');
        }

        // "Jenis Barang" di sini sebenarnya merepresentasikan jenis kayu,
        // tapi disimpan lewat tabel jenis_barang (bukan jenis_kayus) — dicocokkan by nama,
        // sama seperti pola di SerahTerimaHpRelationManager.
        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $lembar = (float) $record->jumlah;
        $kubikasi = $lembar * (float) $ukuran->kubikasi / 10000000;

        app(StokGudangSatuService::class)->tambah(
            idJenisKayu: $jenisKayu->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembar,
            kubikasi: $kubikasi,
            keterangan: 'Terima barang dari Pilih Plywood - Gudang Satu (Serah Terima #'.$record->id.')',
            referensi: $record,
        );
    }

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->id;
        $isNyusup = $this->isNyusupContext();
        $foreignKey = $this->ownerForeignKey();

        return $table
            ->modifyQueryUsing(function ($query) use ($ownerId, $isNyusup, $foreignKey) {
                // Reset constraint bawaan dari relasi dasar (WHERE id_produksi_terima_gudang_satu = ownerId),
                // supaya kondisi "masih menunggu" (diterima_oleh = '-') tidak ikut ke-AND-kan
                // dan bisa muncul walau kolom FK terkait masih NULL.
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                $query->with([
                    'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                    'hasilPilihPlywood.barangSetengahJadiHp.grade',
                    'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                    'hasilPilihPlywood.produksiPilihPlywood',
                    'triplekMutasiKeluar.jenisKayu',
                ]);

                // Filter tujuan:
                //  - Konteks Nyusup       : tujuan = 'nyusup' ATAU 'triplek_jadi'.
                //  - Konteks Gudang Satu  : tujuan selain 'nyusup' (null / gudang_satu /
                //                           'triplek_jadi' otomatis lolos di sini).
                // Dengan begitu barang dari Gudang Triplek Jadi muncul di KEDUA
                // antrean sampai salah satu menerimanya.
                $query->when(
                    $isNyusup,
                    fn ($q) => $q->whereIn('tujuan', ['nyusup', 'triplek_jadi']),
                    fn ($q) => $q->where(function ($sub) {
                        $sub->whereNull('tujuan')->orWhere('tujuan', '!=', 'nyusup');
                    })
                );

                // Tampilkan yang masih menunggu (belum diterima siapapun, bisa diterima produksi manapun)
                // ATAU yang sudah diterima dan memang lengket ke produksi ini.
                return $query->where(function ($mainQuery) use ($ownerId, $foreignKey) {
                    $mainQuery->where('diterima_oleh', '-')
                        ->orWhere($foreignKey, $ownerId);
                })
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $this->isDariTriplek($record)
                        ? ($record->triplekMutasiKeluar?->jenisKayu?->nama_kayu ?? '-')
                        : ($record->barang_setengah_jadi?->jenisBarang?->nama_jenis_barang ?? '-')),

                TextColumn::make('asal')
                    ->label('Asal')
                    ->badge()
                    ->state(fn ($record) => $this->isDariTriplek($record) ? 'Triplek Jadi' : 'Pilih Plywood')
                    ->color(fn ($state) => $state === 'Triplek Jadi' ? 'info' : 'gray'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $this->isDariTriplek($record)
                        ? ($record->triplekMutasiKeluar?->kw_grade ?? '-')
                        : ($record->barang_setengah_jadi?->grade?->nama_grade ?? '-')),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            $m = $record->triplekMutasiKeluar;
                            return $m
                                ? ($m->panjang + 0) . '×' . ($m->lebar + 0) . '×' . ($m->tebal + 0)
                                : '-';
                        }
                        return $record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-';
                    }),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->state(fn ($record) => $this->isDariTriplek($record)
                        ? 'Triplek Jadi'
                        : ($record->hasilPilihPlywood?->kondisi ?? '-'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('jumlah')
                    ->label('Jumlah Bagus')
                    ->numeric()
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
                        'Diterima' => 'success',
                        'Menunggu' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->headerActions([
                // Tidak ada CreateAction — barang masuk otomatis dari sisi Pilih Plywood /
                // Hasil Terima Gudang Satu / Mutasi Keluar Gudang Triplek Jadi.
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
                                    Placeholder::make('preview_jenis_barang')
                                        ->label('Jenis Barang')
                                        ->content($preview['jenis_barang']),

                                    Placeholder::make('preview_grade')
                                        ->label('Grade')
                                        ->content($preview['grade']),

                                    Placeholder::make('preview_ukuran')
                                        ->label('Ukuran')
                                        ->content($preview['ukuran']),

                                    Placeholder::make('preview_kondisi')
                                        ->label('Kondisi')
                                        ->content($preview['kondisi']),

                                    Placeholder::make('preview_jenis_cacat')
                                        ->label('Jenis Cacat')
                                        ->content($preview['jenis_cacat']),

                                    Placeholder::make('preview_jumlah')
                                        ->label('Jumlah Bagus')
                                        ->content($preview['jumlah']),

                                    Placeholder::make('preview_dari_produksi')
                                        ->label('Asal')
                                        ->content($preview['dari_produksi']),
                                ]),
                        ];
                    })
                    // Hanya muncul kalau memang masih menunggu (belum lengket ke produksi manapun).
                    ->visible(fn ($record) => $record?->diterima_oleh === '-')
                    ->action(function ($record) use ($ownerId, $foreignKey, $isNyusup) {
                        try {
                            DB::transaction(function () use ($record, $ownerId, $foreignKey, $isNyusup) {
                                // Lock + re-check: mencegah 2 produksi menerima barang yang sama
                                // secara bersamaan (race condition). Untuk barang asal Triplek Jadi,
                                // lock inilah yang menjamin barang otomatis "hilang" dari antrean
                                // satunya begitu diklaim di sini.
                                $fresh = SerahTerimaGudangSatu::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diambil produksi lain.');
                                }

                                $lokasi = $isNyusup ? 'Nyusup' : 'Gudang Satu';

                                // Begitu diterima, record ini lengket permanen ke produksi ini
                                // (kolom FK yang dipakai tergantung konteks: nyusup atau gudang satu).
                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name . ' - ' . $lokasi,
                                    $foreignKey => $ownerId,
                                    'status' => 'Diterima',
                                ]);

                                if ($fresh->id_triplek_mutasi_keluar !== null) {
                                    // ── Barang asal GUDANG TRIPLEK JADI ──
                                    // Potong stok triplek + tulis log 'keluar'. Kalau diterima
                                    // di Gudang Satu, sekalian tambah stok plywood siap jual.
                                    app(TerimaTriplekJadiService::class)
                                        ->konfirmasi($fresh, tambahStokGudangSatu: ! $isNyusup);
                                } elseif (! $isNyusup) {
                                    // ── Barang asal PILIH PLYWOOD (logika lama) ──
                                    // Tambah stok plywood siap jual — hanya konteks Gudang Satu.
                                    $this->tambahStokPlywoodSiapJual($fresh);
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
                        ->label('Hapus Terpilih')
                        ->visible(fn () => Auth::user()->hasAnyRole(['super-admin', 'admin'])),
                ]),
            ]);
    }
}