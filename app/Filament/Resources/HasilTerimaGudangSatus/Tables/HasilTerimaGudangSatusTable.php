<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Tables;

use App\Models\BahanTerimaGudangSatu;
use App\Models\HasilTerimaGudangSatu;
use App\Models\JenisKayu;
use App\Models\SerahTerimaGudangSatu;
use App\Services\StokGudangSatuService;
use App\Services\StokPlywoodSiapJualService;
use App\Services\TerimaGudangSatuService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HasilTerimaGudangSatusTable
{
    /**
     * Proses penyesuaian grade (turun/naik grade) untuk stok Gudang Satu
     * sebelum barang diserahkan keluar dari Gudang Satu.
     */
    protected static function prosesPenyesuaianGrade(
        HasilTerimaGudangSatu $record
    ): void {
        $record->loadMissing([
            'jenisBarang',
            'grade',
            'ukuran',
        ]);

        $gradeHasil = $record->grade?->nama_grade;

        if (! $gradeHasil) {
            throw new \RuntimeException(
                'Grade pada hasil tidak ditemukan, penyesuaian stok tidak bisa dilakukan.'
            );
        }

        $stokService = app(StokGudangSatuService::class);

        $bahanList = BahanTerimaGudangSatu::with([
            'barangSetengahJadiHp.jenisBarang',
            'barangSetengahJadiHp.grade',
            'barangSetengahJadiHp.ukuran',
        ])
            ->where(
                'id_hasil_terima_gudang_satu',
                $record->id
            )
            ->get();

        foreach ($bahanList as $bahan) {
            $bsj = $bahan->barangSetengahJadiHp;

            if (
                ! $bsj ||
                ! $bsj->ukuran ||
                ! $bsj->grade ||
                ! $bsj->jenisBarang
            ) {
                throw new \RuntimeException(
                    "Data bahan (#{$bahan->id}) tidak lengkap, stok tidak bisa disesuaikan."
                );
            }

            // Jika grade bahan sama dengan grade hasil, tidak perlu disesuaikan.
            if ($bsj->grade->nama_grade === $gradeHasil) {
                continue;
            }

            $jenisKayuBahan = JenisKayu::where(
                'nama_kayu',
                $bsj->jenisBarang->nama_jenis_barang
            )->first();

            if (! $jenisKayuBahan) {
                throw new \RuntimeException(
                    "Jenis kayu \"{$bsj->jenisBarang->nama_jenis_barang}\" (bahan) tidak ditemukan di data Jenis Kayu."
                );
            }

            $lembarBahan = (float) $bahan->jumlah;

            $kubikasiBahan =
                $lembarBahan *
                (float) $bsj->ukuran->kubikasi /
                10000000;

            // Kurangi stok grade lama.
            $stokService->kurang(
                idJenisKayu: $jenisKayuBahan->id,
                panjang: $bsj->ukuran->panjang,
                lebar: $bsj->ukuran->lebar,
                tebal: $bsj->ukuran->tebal,
                kwGrade: $bsj->grade->nama_grade,
                lembar: $lembarBahan,
                kubikasi: $kubikasiBahan,
                keterangan:
                    'Penyesuaian grade (turun/naik) — pemakaian bahan untuk Hasil Terima Gudang Satu #' .
                    $record->id .
                    ' sebelum diserahkan ke Gudang',
                referensi: $bahan,
            );

            $ukuranHasil = $record->ukuran;

            if (! $ukuranHasil) {
                throw new \RuntimeException(
                    'Data ukuran pada hasil tidak lengkap, penyesuaian stok tidak bisa dilakukan.'
                );
            }

            $jenisKayuHasil = JenisKayu::where(
                'nama_kayu',
                $record->jenisBarang?->nama_jenis_barang
            )->first();

            if (! $jenisKayuHasil) {
                throw new \RuntimeException(
                    "Jenis kayu \"{$record->jenisBarang?->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu."
                );
            }

            $kubikasiBaru =
                $lembarBahan *
                (float) $ukuranHasil->kubikasi /
                10000000;

            // Tambah stok grade baru.
            $stokService->tambah(
                idJenisKayu: $jenisKayuHasil->id,
                panjang: $ukuranHasil->panjang,
                lebar: $ukuranHasil->lebar,
                tebal: $ukuranHasil->tebal,
                kwGrade: $gradeHasil,
                lembar: $lembarBahan,
                kubikasi: $kubikasiBaru,
                keterangan:
                    'Penyesuaian grade (turun/naik) — hasil sortir Hasil Terima Gudang Satu #' .
                    $record->id .
                    ' sebelum diserahkan ke Gudang',
                referensi: $record,
            );
        }
    }

    public static function configure(Table $table): Table
    {
        return $table

            ->modifyQueryUsing(
                fn ($query) => $query->with([
                    'grade.kategoriBarang',
                    'jenisBarang',
                    'ukuran',
                    'serahTerimaGudangSatu',
                    'bahan.barangSetengahJadiHp.grade.kategoriBarang',
                ])
            )

            ->columns([
                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->getStateUsing(
                        fn ($record) =>
                            $record->ukuran?->dimensi ?? '-'
                    )
                    ->sortable(),

                TextColumn::make('jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('grade_awal')
                    ->label('Grade Awal')
                    ->getStateUsing(function ($record) {
                        $gradeAwal =
                            $record->bahan?->barangSetengahJadiHp?->grade;

                        if (! $gradeAwal) {
                            return '-';
                        }

                        return
                            ($gradeAwal->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            . ' | ' .
                            ($gradeAwal->nama_grade ?? '-');
                    }),

                TextColumn::make('grade.nama_grade')
                    ->label('Grade Sekarang')
                    ->getStateUsing(
                        fn ($record) =>
                            ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            . ' | ' .
                            ($record->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->searchable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        return $serah->diterima_oleh === '-'
                            ? 'Menunggu Diterima'
                            : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-'
                            ? 'warning'
                            : 'success';
                    }),
            ])

            ->filters([
                //
            ])

            ->headerActions([

                /*
                 * =========================================================
                 * TOMBOL PENGEMBALIAN KE GUDANG
                 * =========================================================
                 */

                Action::make('kembalikanKeGudang')
                    ->label('Kembalikan ke Gudang')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->modalHeading('Kembalikan Sisa Bahan ke Gudang')
                    ->modalDescription(
                        'Pilih bahan yang masih memiliki sisa dan tentukan jumlah yang akan dikembalikan ke Gudang Satu.'
                    )
                    ->modalSubmitActionLabel('Kembalikan')
                    ->hidden(
                        fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    )
                    ->form(function ($livewire) {
                        $ownerId = $livewire->ownerRecord?->id;
                        return [
                            /*
                             * Pilihan bahan.
                             *
                             * Yang ditampilkan hanya bahan dari produksi
                             * yang sedang dibuka dan masih mempunyai sisa
                             * pada SerahTerimaGudangSatu.
                             */
                            Select::make('id_serah_terima_gudang_satu')
                                ->label('Bahan')
                                ->required()
                                ->live()
                                ->helperText(
                                    'Pilih bahan yang masih memiliki sisa untuk dikembalikan ke Gudang Satu.'
                                )
                                ->options(function () use ($ownerId) {
                                    if (! $ownerId) {
                                        return [];
                                    }
                                    return BahanTerimaGudangSatu::query()
                                        ->where(
                                            'id_produksi_terima_gudang_satu',
                                            $ownerId
                                        )
                                        ->with([
                                            'serahTerimaGudangSatu',
                                            'barangSetengahJadiHp.jenisBarang',
                                            'barangSetengahJadiHp.grade.kategoriBarang',
                                            'barangSetengahJadiHp.ukuran',
                                        ])
                                        ->get()
                                        ->mapWithKeys(function ($bahan) {
                                            $serah =
                                                $bahan->serahTerimaGudangSatu;
                                            if (! $serah) {
                                                return [];
                                            }
                                            $sisa = (float) $serah->sisa;
                                            if ($sisa <= 0) {
                                                return [];
                                            }
                                            $barang =
                                                $bahan->barangSetengahJadiHp;
                                            $jenis =
                                                $barang?->jenisBarang?->nama_jenis_barang
                                                ?? '-';
                                            $grade =
                                                $barang?->grade?->nama_grade
                                                ?? '-';
                                            $ukuran =
                                                $barang?->ukuran?->dimensi
                                                ?? '-';
                                            $palet =
                                                $bahan->no_palet ?? '-';

                                            return [
                                                $serah->id =>
                                                    "Palet {$palet} | {$jenis} | {$ukuran} | Grade {$grade} | Sisa {$sisa} Lbr",
                                            ];
                                        })
                                        ->toArray();
                                })
                                ->afterStateUpdated(
                                    function ($state, callable $set) {
                                        if (! $state) {
                                            $set(
                                                'maks_pengembalian',
                                                null
                                            );
                                            return;
                                        }
                                        $serah =
                                            SerahTerimaGudangSatu::find(
                                                $state
                                            );
                                        $set(
                                            'maks_pengembalian',
                                            $serah?->sisa ?? 0
                                        );
                                    }
                                ),

                            /*
                             * Jumlah yang akan dikembalikan.
                             */
                            TextInput::make('jumlah_kembali')
                                ->label('Jumlah Dikembalikan (Lembar)')
                                ->numeric()
                                ->required()
                                ->minValue(1)

                                ->helperText(
                                    fn (Get $get) =>
                                        $get('maks_pengembalian')
                                            ? 'Maks. bisa dikembalikan: ' .
                                                $get('maks_pengembalian') .
                                                ' lembar.'
                                            : 'Pilih bahan terlebih dahulu.'
                                )

                                ->rules([
                                    fn (Get $get) =>
                                        function (
                                            string $attribute,
                                            $value,
                                            \Closure $fail
                                        ) use ($get) {

                                            $maks =
                                                (float) (
                                                    $get(
                                                        'maks_pengembalian'
                                                    ) ?? 0
                                                );

                                            if (
                                                (float) $value > $maks
                                            ) {
                                                $fail(
                                                    "Jumlah melebihi sisa yang tersedia ({$maks} lembar)."
                                                );
                                            }
                                        },
                                ]),

                            /*
                             * Nilai ini hanya digunakan untuk membantu
                             * validasi form.
                             */

                            Hidden::make('maks_pengembalian'),
                        ];
                    })

                    ->action(function (array $data, $livewire) {

                        $idSerah =
                            $data['id_serah_terima_gudang_satu']
                            ?? null;

                        if (! $idSerah) {

                            Notification::make()
                                ->title('Gagal Mengembalikan')
                                ->body('Bahan belum dipilih.')
                                ->danger()
                                ->send();

                            return;
                        }

                        try {

                            $serah =
                                SerahTerimaGudangSatu::findOrFail(
                                    $idSerah
                                );

                            $jumlah =
                                (float) $data['jumlah_kembali'];

                            /*
                             * Owner record adalah ProduksiTerimaGudangSatu
                             * yang sedang dibuka.
                             */

                            $produksi =
                                $livewire->ownerRecord;

                            /*
                             * Jalankan service pengembalian.
                             *
                             * Service akan:
                             * 1. Mengunci data SerahTerimaGudangSatu.
                             * 2. Mengecek sisa terbaru.
                             * 3. Membuat HasilTerimaGudangSatu baru.
                             * 4. Menambah jumlah_dikembalikan.
                             */

                            app(TerimaGudangSatuService::class)
                                ->kembaliKeGudang(
                                    $serah,
                                    $jumlah,
                                    $produksi
                                );

                            Notification::make()
                                ->title(
                                    'Sisa bahan berhasil dikembalikan ke Gudang'
                                )
                                ->body(
                                    "Sebanyak {$jumlah} lembar berhasil dikembalikan."
                                )
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {

                            Notification::make()
                                ->title('Gagal Mengembalikan')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                /*
                 * =========================================================
                 * CREATE ACTION MILIK KODE SEBELUMNYA
                 * =========================================================
                 */

                CreateAction::make()
                    ->after(
                        function (
                            HasilTerimaGudangSatu $record,
                            array $data
                        ) {

                            $bahanData =
                                $data['bahan'] ?? null;

                            if (
                                $bahanData &&
                                ! empty(
                                    $bahanData[
                                        'id_serah_terima_gudang_satu'
                                    ]
                                )
                            ) {

                                $serahTerima =
                                    SerahTerimaGudangSatu::find(
                                        $bahanData[
                                            'id_serah_terima_gudang_satu'
                                        ]
                                    );

                                $record->bahan()->create([
                                    'id_produksi_terima_gudang_satu' =>
                                        $record->id_produksi_terima_gudang_satu,

                                    'id_serah_terima_gudang_satu' =>
                                        $bahanData[
                                            'id_serah_terima_gudang_satu'
                                        ],

                                    'id_barang_setengah_jadi_hp' =>
                                        $bahanData[
                                            'id_barang_setengah_jadi_hp'
                                        ]
                                        ??
                                        $serahTerima?->barangSetengahJadi?->id,

                                    'no_palet' =>
                                        $bahanData['no_palet']
                                        ?? null,

                                    'jumlah' =>
                                        $bahanData['jumlah']
                                        ?? null,
                                ]);
                            }
                        }
                    )

                    ->hidden(
                        fn ($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([

                /*
                 * =========================================================
                 * TOMBOL SERAH
                 * =========================================================
                 */

                Action::make('serah')
                    ->label('Serah')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-right-circle')

                    ->visible(
                        fn ($record) =>
                            ! $record->serahTerimaGudangSatu
                    )

                    ->modalHeading('Serah Barang')

                    ->modalDescription(
                        fn ($record) =>
                            'Jumlah: ' .
                            ($record->jumlah ?? 0) .
                            ' pcs. Pilih tujuan penyerahan barang ini.'
                    )

                    ->modalSubmitActionLabel('Serah')

                    ->form(function ($record) {

                        return [

                            Placeholder::make('grade_detail')
                                ->label('Grade')
                                ->content(
                                    ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' .
                                    ($record->grade?->nama_grade ?? '-')
                                ),

                            Placeholder::make('jenis_detail')
                                ->label('Jenis Barang')
                                ->content(
                                    $record->jenisBarang?->nama_jenis_barang
                                    ?? '-'
                                ),

                            Placeholder::make('ukuran_detail')
                                ->label('Ukuran')
                                ->content(
                                    $record->ukuran?->dimensi
                                    ?? '-'
                                ),

                            Placeholder::make('jumlah_detail')
                                ->label('Jumlah')
                                ->content(
                                    (string) (
                                        $record->jumlah ?? 0
                                    )
                                ),

                            Radio::make('serah_ke')
                                ->label('Serah Ke')
                                ->options([
                                    'nyusup' =>
                                        'Serah ke Nyusup',

                                    'gudang' =>
                                        'Serah ke Gudang Plywood Siap Jual',
                                ])
                                ->default('nyusup')
                                ->required()
                                ->live(),

                            Placeholder::make('warning')
                                ->label('⚠️ Perhatian')
                                ->content(
                                    'Tindakan ini akan langsung dianggap DITERIMA (auto-terima) dan TIDAK BISA dibatalkan. Jika ada perbedaan grade antara bahan asal dan hasil ini, stok Gudang Satu akan disesuaikan terlebih dahulu, lalu stok Gudang Satu pada grade hasil ini akan dikurangi dan stok Plywood Siap Jual akan bertambah.'
                                )
                                ->visible(
                                    fn ($get) =>
                                        $get('serah_ke') === 'gudang'
                                ),

                            Checkbox::make('konfirmasi_ganda')
                                ->label(
                                    'Saya yakin data sudah benar dan menyetujui penyesuaian grade serta perubahan stok ini secara langsung.'
                                )
                                ->visible(
                                    fn ($get) =>
                                        $get('serah_ke') === 'gudang'
                                )
                                ->accepted(
                                    fn ($get) =>
                                        $get('serah_ke') === 'gudang'
                                )
                                ->required(
                                    fn ($get) =>
                                        $get('serah_ke') === 'gudang'
                                ),
                        ];
                    })

                    ->action(function (
                        $record,
                        array $data
                    ) {

                        if (
                            $data['serah_ke'] === 'nyusup'
                        ) {

                            try {

                                SerahTerimaGudangSatu::create([
                                    'id_hasil_terima_gudang_satu' =>
                                        $record->id,

                                    'tujuan' =>
                                        'nyusup',

                                    'diserahkan_oleh' =>
                                        Auth::user()->name,

                                    'diterima_oleh' =>
                                        '-',

                                    'status' =>
                                        'Menunggu',
                                ]);

                                Notification::make()
                                    ->title(
                                        'Berhasil diserahkan (Nyusup)'
                                    )
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {

                                Notification::make()
                                    ->title('Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }

                        } elseif (
                            $data['serah_ke'] === 'gudang'
                        ) {

                            if (
                                empty(
                                    $data['konfirmasi_ganda']
                                )
                            ) {

                                throw ValidationException::withMessages([
                                    'konfirmasi_ganda' =>
                                        'Anda harus menyetujui konfirmasi sebelum melanjutkan.',
                                ]);
                            }

                            try {

                                DB::transaction(
                                    function () use ($record) {

                                        $panjang =
                                            $record->ukuran?->panjang
                                            ?? 0;

                                        $lebar =
                                            $record->ukuran?->lebar
                                            ?? 0;

                                        $tebal =
                                            $record->ukuran?->tebal
                                            ?? 0;

                                        $kwGrade =
                                            $record->grade?->nama_grade
                                            ?? '-';

                                        $namaJenisBarang =
                                            $record->jenisBarang?->nama_jenis_barang;

                                        $idJenisKayu =
                                            JenisKayu::where(
                                                'nama_kayu',
                                                $namaJenisBarang
                                            )->value('id');

                                        if (! $idJenisKayu) {

                                            throw new \RuntimeException(
                                                "Jenis kayu \"{$namaJenisBarang}\" tidak ditemukan di master jenis kayu."
                                            );
                                        }

                                        $lembar =
                                            $record->jumlah
                                            ?? 0;

                                        $penyerah =
                                            Auth::user()->name;

                                        /*
                                         * 1. Catat serah terima.
                                         */

                                        $serahTerima =
                                            SerahTerimaGudangSatu::create([
                                                'id_hasil_terima_gudang_satu' =>
                                                    $record->id,

                                                'tujuan' =>
                                                    'gudang',

                                                'diserahkan_oleh' =>
                                                    $penyerah,

                                                'diterima_oleh' =>
                                                    $penyerah,

                                                'status' =>
                                                    'Diterima',
                                            ]);

                                        /*
                                         * 2. Penyesuaian grade.
                                         */

                                        static::prosesPenyesuaianGrade(
                                            $record
                                        );

                                        /*
                                         * 3. Kurangi stok Gudang Satu.
                                         */

                                        $kubikasi =
                                            $lembar *
                                            (float) (
                                                $record->ukuran?->kubikasi
                                                ?? 0
                                            ) /
                                            10000000;

                                        app(
                                            StokGudangSatuService::class
                                        )->kurang(
                                            idJenisKayu:
                                                $idJenisKayu,

                                            panjang:
                                                $panjang,

                                            lebar:
                                                $lebar,

                                            tebal:
                                                $tebal,

                                            kwGrade:
                                                $kwGrade,

                                            lembar:
                                                $lembar,

                                            kubikasi:
                                                $kubikasi,

                                            keterangan:
                                                'Serah terima dari Terima Gudang Satu ke Gudang',

                                            referensi:
                                                $serahTerima,
                                        );

                                        /*
                                         * 4. Tambah stok Plywood Siap Jual.
                                         */

                                        app(
                                            StokPlywoodSiapJualService::class
                                        )->tambah(
                                            idJenisKayu:
                                                $idJenisKayu,

                                            panjang:
                                                $panjang,

                                            lebar:
                                                $lebar,

                                            tebal:
                                                $tebal,

                                            kwGrade:
                                                $kwGrade,

                                            lembar:
                                                $lembar,

                                            keterangan:
                                                'Serah terima dari Terima Gudang Satu ke Gudang',

                                            referensi:
                                                $serahTerima,
                                        );
                                    }
                                );

                                Notification::make()
                                    ->title(
                                        'Barang berhasil diserahkan ke Gudang, stok Gudang Satu disesuaikan/berkurang, dan stok siap jual bertambah'
                                    )
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {

                                Notification::make()
                                    ->title(
                                        'Gagal menyerahkan barang ke Gudang'
                                    )
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                /*
                 * =========================================================
                 * EDIT
                 * =========================================================
                 */

                EditAction::make()

                    ->mutateRecordDataUsing(
                        function (
                            array $data,
                            HasilTerimaGudangSatu $record
                        ): array {

                            $bahan = $record->bahan;

                            if ($bahan) {

                                $data['bahan'] = [

                                    'id_serah_terima_gudang_satu' =>
                                        $bahan->id_serah_terima_gudang_satu,

                                    'id_barang_setengah_jadi_hp' =>
                                        $bahan->id_barang_setengah_jadi_hp,

                                    'no_palet' =>
                                        $bahan->no_palet,

                                    'jumlah' =>
                                        $bahan->jumlah,
                                ];
                            }

                            return $data;
                        }
                    )

                    ->after(
                        function (
                            HasilTerimaGudangSatu $record,
                            array $data
                        ) {

                            $bahanData =
                                $data['bahan'] ?? null;

                            if (
                                $bahanData &&
                                ! empty(
                                    $bahanData[
                                        'id_serah_terima_gudang_satu'
                                    ]
                                )
                            ) {

                                $serahTerima =
                                    SerahTerimaGudangSatu::find(
                                        $bahanData[
                                            'id_serah_terima_gudang_satu'
                                        ]
                                    );

                                $record->bahan()->updateOrCreate(
                                    [],
                                    [

                                        'id_produksi_terima_gudang_satu' =>
                                            $record->id_produksi_terima_gudang_satu,

                                        'id_serah_terima_gudang_satu' =>
                                            $bahanData[
                                                'id_serah_terima_gudang_satu'
                                            ],

                                        'id_barang_setengah_jadi_hp' =>
                                            $bahanData[
                                                'id_barang_setengah_jadi_hp'
                                            ]
                                            ??
                                            $serahTerima?->barangSetengahJadi?->id,

                                        'no_palet' =>
                                            $bahanData['no_palet']
                                            ?? null,

                                        'jumlah' =>
                                            $bahanData['jumlah']
                                            ?? null,
                                    ]
                                );
                            }
                        }
                    )

                    ->hidden(
                        function (
                            $record,
                            $livewire
                        ) {

                            if (
                                $livewire->ownerRecord?->validasiTerakhir?->status ===
                                'divalidasi'
                            ) {
                                return true;
                            }

                            $serah =
                                $record->serahTerimaGudangSatu;

                            return
                                $serah &&
                                $serah->diterima_oleh !== '-';
                        }
                    ),

                /*
                 * =========================================================
                 * DELETE
                 * =========================================================
                 */

                DeleteAction::make()

                    ->before(
                        function (
                            HasilTerimaGudangSatu $record
                        ) {
                            $record->bahan()?->delete();
                        }
                    )

                    ->hidden(
                        fn (
                            $record,
                            $livewire
                        ) =>
                            $record->serahTerimaGudangSatu ||
                            $livewire->ownerRecord?->validasiTerakhir?->status ===
                                'divalidasi'
                    ),
            ])

            ->toolbarActions([

                BulkActionGroup::make([

                    DeleteBulkAction::make()

                        ->hidden(
                            fn ($livewire) =>
                                $livewire->ownerRecord?->validasiTerakhir?->status ===
                                    'divalidasi'
                        ),
                ]),
            ]);
    }
}