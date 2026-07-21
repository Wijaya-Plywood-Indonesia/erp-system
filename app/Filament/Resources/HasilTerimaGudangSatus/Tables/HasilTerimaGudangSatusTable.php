<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Tables;

use App\Models\BahanTerimaGudangSatu;
use App\Models\HasilTerimaGudangSatu;
use App\Models\JenisKayu;
use App\Models\SerahTerimaGudangSatu;
use App\Services\StokGudangSatuService;
use App\Services\StokPlywoodSiapJualService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HasilTerimaGudangSatusTable
{
    /**
     * Proses penyesuaian grade (turun/naik grade) untuk stok Gudang Satu SEBELUM
     * barang diserahkan keluar dari Gudang Satu (mis. ke Gudang Plywood Siap Jual).
     *
     * Logic ini adalah duplikasi dari
     * SerahTerimaGudangSatuRelationManager::prosesStokHasilTerimaGudangSatu(),
     * karena alur "Serah ke Gudang" di tabel ini bersifat auto-terima dan TIDAK
     * pernah melewati RelationManager tersebut — jadi penyesuaian grade harus
     * dijalankan manual di sini juga, supaya stok Gudang Satu tetap konsisten
     * sebelum item ditarik keluar sebagai stok siap jual.
     *
     * Untuk tiap bahan (asal) dari hasil ini:
     *  - Kalau grade bahan BERBEDA dari grade hasil: kurangi stok grade lama,
     *    lalu tambahkan stok grade baru (sejumlah bahan tsb) ke Gudang Satu.
     *  - Kalau grade bahan SAMA dengan grade hasil: SKIP — tidak ada
     *    pengurangan maupun penambahan, karena stoknya sudah "benar" di grade itu.
     */
    protected static function prosesPenyesuaianGrade(HasilTerimaGudangSatu $record): void
    {
        $record->loadMissing(['jenisBarang', 'grade', 'ukuran']);

        $gradeHasil = $record->grade?->nama_grade;

        if (! $gradeHasil) {
            throw new \RuntimeException('Grade pada hasil tidak ditemukan, penyesuaian stok tidak bisa dilakukan.');
        }

        $stokService = app(StokGudangSatuService::class);

        $bahanList = BahanTerimaGudangSatu::with([
            'barangSetengahJadiHp.jenisBarang',
            'barangSetengahJadiHp.grade',
            'barangSetengahJadiHp.ukuran',
        ])
            ->where('id_hasil_terima_gudang_satu', $record->id)
            ->get();

        foreach ($bahanList as $bahan) {
            $bsj = $bahan->barangSetengahJadiHp;

            if (! $bsj || ! $bsj->ukuran || ! $bsj->grade || ! $bsj->jenisBarang) {
                throw new \RuntimeException("Data bahan (#{$bahan->id}) tidak lengkap, stok tidak bisa disesuaikan.");
            }

            // Grade bahan (lama) sama dengan grade hasil (baru) → skip,
            // tidak perlu dikurangi & ditambah lagi.
            if ($bsj->grade->nama_grade === $gradeHasil) {
                continue;
            }

            $jenisKayuBahan = JenisKayu::where('nama_kayu', $bsj->jenisBarang->nama_jenis_barang)->first();

            if (! $jenisKayuBahan) {
                throw new \RuntimeException("Jenis kayu \"{$bsj->jenisBarang->nama_jenis_barang}\" (bahan) tidak ditemukan di data Jenis Kayu.");
            }

            $lembarBahan = (float) $bahan->jumlah;
            $kubikasiBahan = $lembarBahan * (float) $bsj->ukuran->kubikasi / 10000000;

            // 🔻 Kurangi stok grade LAMA
            $stokService->kurang(
                idJenisKayu: $jenisKayuBahan->id,
                panjang: $bsj->ukuran->panjang,
                lebar: $bsj->ukuran->lebar,
                tebal: $bsj->ukuran->tebal,
                kwGrade: $bsj->grade->nama_grade,
                lembar: $lembarBahan,
                kubikasi: $kubikasiBahan,
                keterangan: 'Penyesuaian grade (turun/naik) — pemakaian bahan untuk Hasil Terima Gudang Satu #'.$record->id.' sebelum diserahkan ke Gudang',
                referensi: $bahan,
            );

            // 🔺 Tambah stok grade BARU (mengikuti grade hasil), sejumlah bahan ini
            $ukuranHasil = $record->ukuran;

            if (! $ukuranHasil) {
                throw new \RuntimeException('Data ukuran pada hasil tidak lengkap, penyesuaian stok tidak bisa dilakukan.');
            }

            $jenisKayuHasil = JenisKayu::where('nama_kayu', $record->jenisBarang?->nama_jenis_barang)->first();

            if (! $jenisKayuHasil) {
                throw new \RuntimeException("Jenis kayu \"{$record->jenisBarang?->nama_jenis_barang}\" tidak ditemukan di data Jenis Kayu.");
            }

            $kubikasiBaru = $lembarBahan * (float) $ukuranHasil->kubikasi / 10000000;

            $stokService->tambah(
                idJenisKayu: $jenisKayuHasil->id,
                panjang: $ukuranHasil->panjang,
                lebar: $ukuranHasil->lebar,
                tebal: $ukuranHasil->tebal,
                kwGrade: $gradeHasil,
                lembar: $lembarBahan,
                kubikasi: $kubikasiBaru,
                keterangan: 'Penyesuaian grade (turun/naik) — hasil sortir Hasil Terima Gudang Satu #'.$record->id.' sebelum diserahkan ke Gudang',
                referensi: $record,
            );
        }
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'grade.kategoriBarang',
                'jenisBarang',
                'ukuran',
                'serahTerimaGudangSatu',
                'bahan.barangSetengahJadiHp.grade.kategoriBarang',
            ]))
            ->columns([
                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->getStateUsing(fn ($record) => $record->ukuran?->dimensi ?? '-')
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
                        $gradeAwal = $record->bahan?->barangSetengahJadiHp?->grade;

                        if (! $gradeAwal) {
                            return '-';
                        }

                        return ($gradeAwal->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                            .' | '.
                            ($gradeAwal->nama_grade ?? '-');
                    }),

                TextColumn::make('grade.nama_grade')
                    ->label('Grade Sekarang')
                    ->getStateUsing(
                        fn ($record) => ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        .' | '.
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

                        return $serah->diterima_oleh === '-' ? 'Menunggu Diterima' : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(function (HasilTerimaGudangSatu $record, array $data) {
                        $bahanData = $data['bahan'] ?? null;
                        if ($bahanData && ! empty($bahanData['id_serah_terima_gudang_satu'])) {
                            $serahTerima = SerahTerimaGudangSatu::find($bahanData['id_serah_terima_gudang_satu']);
                            $record->bahan()->create([
                                'id_produksi_terima_gudang_satu' => $record->id_produksi_terima_gudang_satu,
                                'id_serah_terima_gudang_satu' => $bahanData['id_serah_terima_gudang_satu'],
                                'id_barang_setengah_jadi_hp' => $bahanData['id_barang_setengah_jadi_hp'] ?? $serahTerima?->barangSetengahJadi?->id,
                                'no_palet' => $bahanData['no_palet'] ?? null,
                                'jumlah' => $bahanData['jumlah'] ?? null,
                            ]);
                        }
                    })
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([

                // 🚚 TOMBOL SERAH
                Action::make('serah')
                    ->label('Serah')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn ($record) => ! $record->serahTerimaGudangSatu)
                    ->modalHeading('Serah Barang')
                    ->modalDescription(fn ($record) => 'Jumlah: '.($record->jumlah ?? 0).' pcs. Pilih tujuan penyerahan barang ini.')
                    ->modalSubmitActionLabel('Serah')
                    ->form(function ($record) {
                        return [
                            Placeholder::make('grade_detail')
                                ->label('Grade')
                                ->content(
                                    ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    .' | '.
                                    ($record->grade?->nama_grade ?? '-')
                                ),

                            Placeholder::make('jenis_detail')
                                ->label('Jenis Barang')
                                ->content($record->jenisBarang?->nama_jenis_barang ?? '-'),

                            Placeholder::make('ukuran_detail')
                                ->label('Ukuran')
                                ->content($record->ukuran?->dimensi ?? '-'),

                            Placeholder::make('jumlah_detail')
                                ->label('Jumlah')
                                ->content((string) ($record->jumlah ?? 0)),

                            Radio::make('serah_ke')
                                ->label('Serah Ke')
                                ->options([
                                    'nyusup' => 'Serah ke Nyusup',
                                    'gudang' => 'Serah ke Gudang',
                                ])
                                ->default('nyusup')
                                ->required()
                                ->live(),

                            // 🔒 Konfirmasi ganda — hanya wajib & tampil saat tujuan "Gudang",
                            // karena aksi ini auto-terima, langsung menjalankan penyesuaian
                            // grade (jika ada), mengurangi stok Gudang Satu, dan menambah
                            // stok Plywood Siap Jual — semuanya TIDAK BISA dibatalkan.
                            Placeholder::make('warning')
                                ->label('⚠️ Perhatian')
                                ->content(
                                    'Tindakan ini akan langsung dianggap DITERIMA (auto-terima) dan '
                                    .'TIDAK BISA dibatalkan. Jika ada perbedaan grade antara bahan asal '
                                    .'dan hasil ini, stok Gudang Satu akan disesuaikan (grade lama '
                                    .'dikurangi, grade baru ditambah) terlebih dahulu, lalu stok Gudang '
                                    .'Satu pada grade hasil ini akan dikurangi sejumlah Jumlah, dan stok '
                                    .'Plywood Siap Jual akan bertambah.'
                                )
                                ->visible(fn ($get) => $get('serah_ke') === 'gudang'),

                            Checkbox::make('konfirmasi_ganda')
                                ->label('Saya yakin data sudah benar dan menyetujui penyesuaian grade serta perubahan stok ini secara langsung.')
                                ->visible(fn ($get) => $get('serah_ke') === 'gudang')
                                ->accepted(fn ($get) => $get('serah_ke') === 'gudang')
                                ->required(fn ($get) => $get('serah_ke') === 'gudang'),
                        ];
                    })
                    ->action(function ($record, array $data) {

                        if ($data['serah_ke'] === 'nyusup') {

                            try {
                                SerahTerimaGudangSatu::create([
                                    'id_hasil_terima_gudang_satu' => $record->id,
                                    'tujuan' => 'nyusup',
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Menunggu',
                                ]);

                                Notification::make()
                                    ->title('Berhasil diserahkan (Nyusup)')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }

                        } elseif ($data['serah_ke'] === 'gudang') {

                            // Validasi server-side: checkbox konfirmasi ganda wajib dicentang
                            if (empty($data['konfirmasi_ganda'])) {
                                throw ValidationException::withMessages([
                                    'konfirmasi_ganda' => 'Anda harus menyetujui konfirmasi sebelum melanjutkan.',
                                ]);
                            }

                            try {
                                DB::transaction(function () use ($record) {

                                    $panjang = $record->ukuran?->panjang ?? 0;
                                    $lebar = $record->ukuran?->lebar ?? 0;
                                    $tebal = $record->ukuran?->tebal ?? 0;
                                    $kwGrade = $record->grade?->nama_grade ?? '-';

                                    $namaJenisBarang = $record->jenisBarang?->nama_jenis_barang;

                                    $idJenisKayu = JenisKayu::where('nama_kayu', $namaJenisBarang)
                                        ->value('id');

                                    if (! $idJenisKayu) {
                                        throw new \RuntimeException("Jenis kayu \"{$namaJenisBarang}\" tidak ditemukan di master jenis kayu.");
                                    }

                                    $lembar = $record->jumlah ?? 0;
                                    $penyerah = Auth::user()->name;

                                    // 1. Catat serah terima dengan tujuan 'gudang'.
                                    // Belum ada fitur "terima" untuk tujuan gudang, jadi
                                    // langsung ditandai diterima oleh pengirim sendiri (auto-terima).
                                    $serahTerima = SerahTerimaGudangSatu::create([
                                        'id_hasil_terima_gudang_satu' => $record->id,
                                        'tujuan' => 'gudang',
                                        'diserahkan_oleh' => $penyerah,
                                        'diterima_oleh' => $penyerah,
                                        'status' => 'Diterima',
                                    ]);

                                    // 2. 🔁 Jalankan penyesuaian grade (turun/naik grade) dulu.
                                    // Ini yang biasanya dijalankan otomatis lewat RelationManager
                                    // saat diterima, tapi karena "Serah ke Gudang" di sini bersifat
                                    // auto-terima (tidak lewat RelationManager), harus dijalankan
                                    // manual di sini supaya stok Gudang Satu tetap konsisten.
                                    // Bahan yang grade-nya SAMA dengan grade hasil otomatis di-skip
                                    // (tidak ada pengurangan/penambahan) di dalam method ini.
                                    static::prosesPenyesuaianGrade($record);

                                    // 3. 🔻 Kurangi stok Gudang Satu (grade hasil ini) sejumlah
                                    // Jumlah yang diserahkan, karena barang ini keluar dari sistem
                                    // Gudang Satu menuju Gudang Plywood Siap Jual.
                                    $kubikasi = $lembar * (float) ($record->ukuran?->kubikasi ?? 0) / 10000000;

                                    app(StokGudangSatuService::class)->kurang(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $panjang,
                                        lebar: $lebar,
                                        tebal: $tebal,
                                        kwGrade: $kwGrade,
                                        lembar: $lembar,
                                        kubikasi: $kubikasi,
                                        keterangan: 'Serah terima dari Terima Gudang Satu ke Gudang',
                                        referensi: $serahTerima,
                                    );

                                    // 4. 🔺 Tambah stok plywood siap jual + catat log (kubikasi dihitung di dalam service)
                                    app(StokPlywoodSiapJualService::class)->tambah(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $panjang,
                                        lebar: $lebar,
                                        tebal: $tebal,
                                        kwGrade: $kwGrade,
                                        lembar: $lembar,
                                        keterangan: 'Serah terima dari Terima Gudang Satu ke Gudang',
                                        referensi: $serahTerima,
                                    );
                                });

                                Notification::make()
                                    ->title('Barang berhasil diserahkan ke Gudang, stok Gudang Satu disesuaikan/berkurang, dan stok siap jual bertambah')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal menyerahkan barang ke Gudang')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, HasilTerimaGudangSatu $record): array {
                        $bahan = $record->bahan;
                        if ($bahan) {
                            $data['bahan'] = [
                                'id_serah_terima_gudang_satu' => $bahan->id_serah_terima_gudang_satu,
                                'id_barang_setengah_jadi_hp' => $bahan->id_barang_setengah_jadi_hp,
                                'no_palet' => $bahan->no_palet,
                                'jumlah' => $bahan->jumlah,
                            ];
                        }
                        return $data;
                    })
                    ->after(function (HasilTerimaGudangSatu $record, array $data) {
                        $bahanData = $data['bahan'] ?? null;
                        if ($bahanData && ! empty($bahanData['id_serah_terima_gudang_satu'])) {
                            $serahTerima = SerahTerimaGudangSatu::find($bahanData['id_serah_terima_gudang_satu']);
                            $record->bahan()->updateOrCreate(
                                [], // match by relationship key
                                [
                                    'id_produksi_terima_gudang_satu' => $record->id_produksi_terima_gudang_satu,
                                    'id_serah_terima_gudang_satu' => $bahanData['id_serah_terima_gudang_satu'],
                                    'id_barang_setengah_jadi_hp' => $bahanData['id_barang_setengah_jadi_hp'] ?? $serahTerima?->barangSetengahJadi?->id,
                                    'no_palet' => $bahanData['no_palet'] ?? null,
                                    'jumlah' => $bahanData['jumlah'] ?? null,
                                ]
                            );
                        }
                    })
                    ->hidden(function ($record, $livewire) {
                        // Sembunyikan kalau sudah divalidasi
                        if ($livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi') {
                            return true;
                        }

                        // Sembunyikan kalau sudah DITERIMA (bukan cuma diserahkan)
                        $serah = $record->serahTerimaGudangSatu;

                        return $serah && $serah->diterima_oleh !== '-';
                    }),

                DeleteAction::make()
                    ->before(function (HasilTerimaGudangSatu $record) {
                        $record->bahan()?->delete();
                    })
                    ->hidden(
                        fn ($record, $livewire) => $record->serahTerimaGudangSatu
                        || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
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
