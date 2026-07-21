<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\RelationManagers;

use App\Models\BahanTerimaGudangSatu;
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
     * Apakah record ini berasal dari jalur Nyusup (DetailBarangDikerjakan)?
     */
    protected function isDariNyusup($record): bool
    {
        return $record->id_hasil_nyusup !== null;
    }

    /**
     * Apakah record ini berasal dari Hasil Terima Gudang Satu (penyesuaian naik/turun grade)?
     */
    protected function isDariHasilTerimaGudangSatu($record): bool
    {
        return $record->id_hasil_terima_gudang_satu !== null;
    }

    /**
     * Ambil data ringkas dari record untuk ditampilkan di preview modal terima.
     * Mendukung empat asal barang: Pilih Plywood (lama), Triplek Jadi, Nyusup,
     * dan Hasil Terima Gudang Satu (penyesuaian grade).
     */
    protected function getPreviewData($record): array
    {
        // ── Asal: Gudang Triplek Jadi ──
        if ($this->isDariTriplek($record)) {
            $mutasi = $record->triplekMutasiKeluar;

            return [
                'jenis_barang' => $mutasi?->jenisKayu?->nama_kayu ?? '-',
                'grade' => $mutasi?->kw_grade ?? '-',
                'ukuran' => $mutasi
                    ? ($mutasi->panjang + 0).'×'.($mutasi->lebar + 0).'×'.($mutasi->tebal + 0)
                    : '-',
                'kondisi' => 'Triplek Jadi',
                'jenis_cacat' => '-',
                'jumlah' => $record->jumlah ?? '-',
                'dari_produksi' => 'Gudang Triplek Jadi (Mutasi #'.$record->id_triplek_mutasi_keluar.')',
            ];
        }

        // ── Asal: Nyusup (DetailBarangDikerjakan) ──
        if ($this->isDariNyusup($record)) {
            $hasilNyusup = $record->hasilNyusup;
            $bsj = $hasilNyusup?->barangSetengahJadiHp;

            return [
                'jenis_barang' => $bsj?->jenisBarang?->nama_jenis_barang ?? '-',
                'grade' => $bsj?->grade?->nama_grade ?? '-',
                'ukuran' => $bsj?->ukuran?->nama_ukuran ?? '-',
                'kondisi' => 'Hasil Nyusup',
                'jenis_cacat' => '-',
                'jumlah' => $record->jumlah ?? '-',
                'dari_produksi' => 'Nyusup (Detail #'.$record->id_hasil_nyusup.')',
            ];
        }

        // ── Asal: Hasil Terima Gudang Satu (penyesuaian naik/turun grade) ──
        if ($this->isDariHasilTerimaGudangSatu($record)) {
            $hasil = $record->hasilTerimaGudangSatu;

            return [
                'jenis_barang' => $hasil?->jenisBarang?->nama_jenis_barang ?? '-',
                'grade' => $hasil?->grade?->nama_grade ?? '-',
                'ukuran' => $hasil?->ukuran?->nama_ukuran ?? '-',
                'kondisi' => 'Penyesuaian Grade Gudang Satu',
                'jenis_cacat' => '-',
                'jumlah' => $record->jumlah ?? '-',
                'dari_produksi' => 'Hasil Terima Gudang Satu (#'.$record->id_hasil_terima_gudang_satu.')',
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

    /**
     * Kurangi & tambah stok Gudang Satu untuk barang yang berasal dari
     * HASIL TERIMA GUDANG SATU (proses sortir ulang / penyesuaian naik-turun grade).
     *
     * Berlaku TERLEPAS dari konteks (Nyusup atau Gudang Satu) — karena ini murni
     * penyesuaian stok internal Gudang Satu, bukan soal transfer ke Nyusup.
     *
     * Alur:
     *  1. Ambil semua "bahan" (BahanTerimaGudangSatu) yang dipakai untuk
     *     menghasilkan HasilTerimaGudangSatu ini — masing-masing mewakili
     *     stok LAMA (grade sebelum disortir ulang) yang harus DIKURANGI
     *     dari Gudang Satu.
     *  2. Tambahkan stok BARU sesuai grade/jenis/ukuran hasil akhir
     *     (HasilTerimaGudangSatu itu sendiri) sejumlah $record->jumlah.
     *
     * 🔒 SKIP: kalau grade bahan (lama) SAMA dengan grade hasil (baru) —
     * artinya barang ini TIDAK naik/turun grade — maka bahan tersebut
     * di-skip total (tidak dikurangi maupun ditambahkan lagi), karena
     * stoknya sudah "benar" di grade itu dan tidak perlu disentuh.
     */
    protected function prosesStokHasilTerimaGudangSatu(SerahTerimaGudangSatu $record): void
    {
        $record->loadMissing([
            'hasilTerimaGudangSatu.jenisBarang',
            'hasilTerimaGudangSatu.grade',
            'hasilTerimaGudangSatu.ukuran',
        ]);

        $hasil = $record->hasilTerimaGudangSatu;

        if (! $hasil) {
            throw new \RuntimeException('Data hasil terima gudang satu tidak ditemukan.');
        }

        $gradeHasil = $hasil->grade?->nama_grade;

        $stokService = app(StokGudangSatuService::class);

        // ── 1. Kurangi stok bahan (asal) yang dipakai untuk hasil ini ──
        // Kecuali bahan yang grade-nya SAMA dengan grade hasil — itu di-skip,
        // tidak perlu naik/turun grade karena memang tidak ada perubahan grade.
        $bahanList = BahanTerimaGudangSatu::with([
            'barangSetengahJadiHp.jenisBarang',
            'barangSetengahJadiHp.grade',
            'barangSetengahJadiHp.ukuran',
        ])
            ->where('id_hasil_terima_gudang_satu', $hasil->id)
            ->get();

        $lembarBerubahGrade = 0.0;

        foreach ($bahanList as $bahan) {
            $bsj = $bahan->barangSetengahJadiHp;

            if (! $bsj || ! $bsj->ukuran || ! $bsj->grade || ! $bsj->jenisBarang) {
                throw new \RuntimeException("Data bahan (#{$bahan->id}) tidak lengkap, stok tidak bisa disesuaikan.");
            }

            // 🔒 SKIP: grade bahan (lama) sama dengan grade hasil (baru) →
            // tidak ada naik/turun grade, jadi tidak perlu dikurangi & ditambah.
            if ($bsj->grade->nama_grade === $gradeHasil) {
                continue;
            }

            $jenisKayuBahan = JenisKayu::where('nama_kayu', $bsj->jenisBarang->nama_jenis_barang)->first();

            if (! $jenisKayuBahan) {
                throw new \RuntimeException("Jenis kayu \"{$bsj->jenisBarang->nama_jenis_barang}\" (bahan) tidak ditemukan di data Jenis Kayu.");
            }

            $lembarBahan = (float) $bahan->jumlah;
            $kubikasiBahan = $lembarBahan * (float) $bsj->ukuran->kubikasi / 10000000;

            $stokService->kurang(
                idJenisKayu: $jenisKayuBahan->id,
                panjang: $bsj->ukuran->panjang,
                lebar: $bsj->ukuran->lebar,
                tebal: $bsj->ukuran->tebal,
                kwGrade: $bsj->grade->nama_grade,
                lembar: $lembarBahan,
                kubikasi: $kubikasiBahan,
                keterangan: 'Pemakaian bahan untuk Hasil Terima Gudang Satu #'.$hasil->id.' (Serah Terima #'.$record->id.')',
                referensi: $bahan,
            );

            // Akumulasi jumlah yang MEMANG berubah grade — hanya sejumlah inilah
            // yang nanti ditambahkan ke grade baru (hasil), supaya tidak dobel
            // menambah stok untuk bahan yang grade-nya sudah sama (di-skip di atas).
            $lembarBerubahGrade += $lembarBahan;
        }

        // ── 2. Tambah stok hasil (baru, sudah disesuaikan grade-nya) ──
        // Hanya untuk porsi yang MEMANG naik/turun grade ($lembarBerubahGrade).
        // Kalau semua bahan grade-nya sama dengan hasil (tidak ada yang berubah),
        // maka $lembarBerubahGrade = 0 dan langkah ini otomatis di-skip juga —
        // karena tidak ada penambahan stok yang perlu dilakukan.
        if ($lembarBerubahGrade <= 0) {
            return;
        }

        $ukuran = $hasil->ukuran;
        $grade = $hasil->grade;
        $jenisBarang = $hasil->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang pada hasil tidak lengkap. Stok tidak dapat ditambahkan.');
        }

        $jenisKayuHasil = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayuHasil) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu. Mohon samakan penamaan atau tambahkan datanya terlebih dahulu.");
        }

        $kubikasiHasil = $lembarBerubahGrade * (float) $ukuran->kubikasi / 10000000;

        $stokService->tambah(
            idJenisKayu: $jenisKayuHasil->id,
            panjang: $ukuran->panjang,
            lebar: $ukuran->lebar,
            tebal: $ukuran->tebal,
            kwGrade: $grade->nama_grade,
            lembar: $lembarBerubahGrade,
            kubikasi: $kubikasiHasil,
            keterangan: 'Terima barang dari Hasil Terima Gudang Satu (penyesuaian grade) - Gudang Satu (Serah Terima #'.$record->id.')',
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
                    'hasilNyusup.barangSetengahJadiHp.jenisBarang',
                    'hasilNyusup.barangSetengahJadiHp.grade',
                    'hasilNyusup.barangSetengahJadiHp.ukuran',
                    'hasilTerimaGudangSatu.jenisBarang',
                    'hasilTerimaGudangSatu.grade',
                    'hasilTerimaGudangSatu.ukuran',
                ]);

                // Filter tujuan:
                //  - 'nyusup'      → HANYA muncul di antrean Nyusup.
                //  - 'gudang_satu' → HANYA muncul di antrean Gudang Satu.
                //  - 'triplek_jadi'→ muncul di KEDUA antrean (Nyusup & Gudang Satu),
                //                    sampai salah satu menerimanya — begitu diterima,
                //                    kolom `tujuan` langsung diubah mengikuti tempat
                //                    penerimaan ('nyusup' atau 'gudang_satu'), sehingga
                //                    otomatis hilang dari antrean lawan cukup lewat
                //                    filter tujuan ini saja (lihat action 'terima').
                //  - 'gudang'      → tujuannya final ke Gudang Plywood Siap Jual,
                //                    TIDAK muncul di antrean manapun di sini.
                $query->when(
                    $isNyusup,
                    fn ($q) => $q->whereIn('tujuan', ['nyusup', 'triplek_jadi']),
                    fn ($q) => $q->whereIn('tujuan', ['gudang_satu', 'triplek_jadi'])
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
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            return $record->triplekMutasiKeluar?->jenisKayu?->nama_kayu ?? '-';
                        }

                        if ($this->isDariNyusup($record)) {
                            return $record->hasilNyusup?->barangSetengahJadiHp?->jenisBarang?->nama_jenis_barang ?? '-';
                        }

                        if ($this->isDariHasilTerimaGudangSatu($record)) {
                            return $record->hasilTerimaGudangSatu?->jenisBarang?->nama_jenis_barang ?? '-';
                        }

                        return $record->barang_setengah_jadi?->jenisBarang?->nama_jenis_barang ?? '-';
                    }),

                TextColumn::make('asal')
                    ->label('Asal')
                    ->badge()
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            return 'Triplek Jadi';
                        }

                        if ($this->isDariNyusup($record)) {
                            return 'Nyusup';
                        }

                        if ($this->isDariHasilTerimaGudangSatu($record)) {
                            return 'Penyesuaian Grade';
                        }

                        return 'Pilih Plywood';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Triplek Jadi' => 'info',
                        'Nyusup' => 'warning',
                        'Penyesuaian Grade' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            return $record->triplekMutasiKeluar?->kw_grade ?? '-';
                        }

                        if ($this->isDariNyusup($record)) {
                            return $record->hasilNyusup?->barangSetengahJadiHp?->grade?->nama_grade ?? '-';
                        }

                        if ($this->isDariHasilTerimaGudangSatu($record)) {
                            return $record->hasilTerimaGudangSatu?->grade?->nama_grade ?? '-';
                        }

                        return $record->barang_setengah_jadi?->grade?->nama_grade ?? '-';
                    }),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            $m = $record->triplekMutasiKeluar;

                            return $m
                                ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0)
                                : '-';
                        }

                        if ($this->isDariNyusup($record)) {
                            return $record->hasilNyusup?->barangSetengahJadiHp?->ukuran?->nama_ukuran ?? '-';
                        }

                        if ($this->isDariHasilTerimaGudangSatu($record)) {
                            return $record->hasilTerimaGudangSatu?->ukuran?->nama_ukuran ?? '-';
                        }

                        return $record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-';
                    }),

                TextColumn::make('kondisi')
                    ->label('Kondisi')
                    ->state(function ($record) {
                        if ($this->isDariTriplek($record)) {
                            return 'Triplek Jadi';
                        }

                        if ($this->isDariNyusup($record)) {
                            return 'Hasil Nyusup';
                        }

                        if ($this->isDariHasilTerimaGudangSatu($record)) {
                            return 'Penyesuaian Grade Gudang Satu';
                        }

                        return $record->hasilPilihPlywood?->kondisi ?? '-';
                    })
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
                // Hasil Terima Gudang Satu / Mutasi Keluar Gudang Triplek Jadi / Nyusup.
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

                                $updateData = [
                                    'diterima_oleh' => Auth::user()->name.' - '.$lokasi,
                                    $foreignKey => $ownerId,
                                    'status' => 'Diterima',
                                ];

                                // Barang asal Triplek Jadi awalnya bertujuan 'triplek_jadi' (bisa
                                // muncul di KEDUA antrean). Begitu diklaim salah satu, ubah tujuan
                                // supaya mengikuti tempat dia diterima — ini yang membuat query
                                // antrean lawan otomatis tidak lagi menampilkan record ini
                                // (optimasi: cukup filter tujuan, tanpa perlu logika tambahan).
                                if ($fresh->tujuan === 'triplek_jadi') {
                                    $updateData['tujuan'] = $isNyusup ? 'nyusup' : 'gudang_satu';
                                }

                                // Begitu diterima, record ini lengket permanen ke produksi ini
                                // (kolom FK yang dipakai tergantung konteks: nyusup atau gudang satu).
                                $fresh->update($updateData);

                                if ($fresh->id_triplek_mutasi_keluar !== null) {
                                    // ── Barang asal GUDANG TRIPLEK JADI ──
                                    // Selalu tambah stok, terlepas dari diterima di Nyusup
                                    // maupun Sampling (Gudang Satu).
                                    app(TerimaTriplekJadiService::class)
                                        ->konfirmasi($fresh, tambahStokGudangSatu: true);

                                } elseif ($fresh->id_hasil_nyusup !== null) {
                                    // ── Barang asal NYUSUP (DetailBarangDikerjakan) ──
                                    // Tidak pernah menambah/mengurangi stok, baik diterima di Nyusup
                                    // maupun di Sampling (Gudang Satu). Sengaja dibiarkan kosong.

                                } elseif ($fresh->id_hasil_terima_gudang_satu !== null) {
                                    // ── Barang asal HASIL TERIMA GUDANG SATU (penyesuaian naik/turun grade) ──
                                    // Kurangi stok bahan lama (grade sebelum disortir ulang) + tambah
                                    // stok hasil baru (grade sesudah disortir ulang). Berlaku terlepas
                                    // dari konteks penerimaan (Nyusup atau Gudang Satu), karena ini
                                    // murni penyesuaian stok internal Gudang Satu.
                                    // Bahan yang grade-nya SAMA dengan grade hasil otomatis di-skip
                                    // di dalam method ini (tidak naik/turun grade).
                                    $this->prosesStokHasilTerimaGudangSatu($fresh);

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
