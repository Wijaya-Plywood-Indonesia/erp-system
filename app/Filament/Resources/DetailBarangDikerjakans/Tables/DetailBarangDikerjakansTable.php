<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Tables;

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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DetailBarangDikerjakansTable
{
    public static function configure(Table $table): Table
    {
        return $table

            /*
            |=====================================================
            | 🔥 GROUP BY PEGAWAI
            |=====================================================
            */
            ->groups([
                Group::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(
                        fn ($record) => $record->pegawaiNyusup?->pegawai?->nama_pegawai
                        ?? 'Pegawai Tidak Diketahui'
                    )
                    ->collapsible(true), // default tertutup
            ])

            /*
            |=====================================================
            | 📋 COLUMNS
            |=====================================================
            */
            ->columns([

                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = $record->barangSetengahJadiHp;

                        if (! $b) {
                            return '-';
                        }

                        $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran = $b->ukuran?->nama_ukuran ?? '-';
                        $grade = $b->grade?->nama_grade ?? '-';
                        $jenis = $b->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} | {$ukuran} | {$grade} | {$jenis}";
                    })
                    ->wrap()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('barangSetengahJadiHp', function (Builder $q) use ($search) {
                            $q->whereHas('ukuran', function ($qu) use ($search) {
                                // Mencari di dimensi fisik (panjang, lebar, tebal)
                                $qu->where('panjang', 'like', "%{$search}%")
                                    ->orWhere('lebar', 'like', "%{$search}%")
                                    ->orWhere('tebal', 'like', "%{$search}%")
                                    ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                            })
                                ->orWhereHas('grade', function ($qg) use ($search) {
                                    $qg->where('nama_grade', 'like', "%{$search}%")
                                        ->orWhereHas('kategoriBarang', fn ($qk) => $qk->where('nama_kategori', 'like', "%{$search}%"));
                                })
                                ->orWhereHas('jenisBarang', fn ($qj) => $qj->where('nama_jenis_barang', 'like', "%{$search}%"));
                        });
                    }),

                TextColumn::make('modal')
                    ->label('Modal')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('hasil')
                    ->label('Hasil')
                    ->numeric()
                    ->alignCenter()
                    ->weight('bold'),
            ])

            /*
            |=====================================================
            | ➕ HEADER ACTIONS
            |=====================================================
            */
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            /*
            |=====================================================
            | ✏️ RECORD ACTIONS
            |=====================================================
            */
            ->recordActions([

                // 🚚 TOMBOL SERAH
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('info')
                    ->hidden(function ($livewire, $record) {
                        // Sembunyikan kalau sudah divalidasi
                        if ($livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi') {
                            return true;
                        }

                        // Sembunyikan kalau sudah pernah diserahkan (ada row terkait)
                        return SerahTerimaGudangSatu::where('id_hasil_nyusup', $record->id)->exists();
                    })
                    ->modalHeading('Serah Barang')
                    ->modalDescription('Detail barang yang akan diserahkan')
                    ->modalSubmitActionLabel('Serah')

                    ->form(function ($record) {
                        $b = $record->barangSetengahJadiHp;

                        $kategori = $b?->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran = $b?->ukuran?->nama_ukuran ?? '-';
                        $grade = $b?->grade?->nama_grade ?? '-';
                        $jenis = $b?->jenisBarang?->nama_jenis_barang ?? '-';

                        return [
                            Placeholder::make('barang_detail')
                                ->label('Barang')
                                ->content("{$kategori} | {$ukuran} | {$grade} | {$jenis}"),

                            Placeholder::make('modal_detail')
                                ->label('Modal')
                                ->content((string) $record->modal),

                            Placeholder::make('hasil_detail')
                                ->label('Hasil')
                                ->content((string) $record->hasil),

                            Radio::make('serah_ke')
                                ->label('Serah Ke')
                                ->options([
                                    'gudang_satu' => 'Serah ke Sampling Plywood',
                                    'gudang' => 'Serah ke Gudang',
                                ])
                                ->required()
                                ->default('gudang_satu')
                                ->live(),

                            // 🔒 Konfirmasi ganda — hanya wajib & tampil saat tujuan "Gudang",
                            // karena aksi ini auto-terima & langsung mengubah stok (tidak bisa dibatalkan)
                            Placeholder::make('warning')
                                ->label('⚠️ Perhatian')
                                ->content(
                                    'Tindakan ini akan langsung dianggap DITERIMA (auto-terima) dan '
                                    .'TIDAK BISA dibatalkan. Stok Gudang Satu akan langsung berkurang '
                                    .'dan stok Plywood Siap Jual akan bertambah sesuai jumlah Hasil.'
                                )
                                ->visible(fn ($get) => $get('serah_ke') === 'gudang'),

                            Checkbox::make('konfirmasi_ganda')
                                ->label('Saya yakin data sudah benar dan menyetujui perubahan stok ini secara langsung.')
                                ->visible(fn ($get) => $get('serah_ke') === 'gudang')
                                ->accepted(fn ($get) => $get('serah_ke') === 'gudang')
                                ->required(fn ($get) => $get('serah_ke') === 'gudang'),
                        ];
                    })
                    ->action(function ($record, array $data) {

                        if ($data['serah_ke'] === 'gudang_satu') {

                            SerahTerimaGudangSatu::create([
                                'id_hasil_pilih_plywood' => null,
                                'id_produksi_terima_gudang_satu' => null,
                                'id_hasil_terima_gudang_satu' => null,
                                'id_triplek_mutasi_keluar' => null,
                                'id_produksi_nyusup' => null,
                                'id_hasil_nyusup' => $record->id,
                                'tujuan' => 'gudang_satu',
                                'diserahkan_oleh' => auth()->user()?->name ?? '-',
                                'diterima_oleh' => '-',
                                'status' => 'menunggu',
                            ]);

                            Notification::make()
                                ->title('Barang berhasil diserahkan ke Terima Gudang Satu')
                                ->success()
                                ->send();

                        } elseif ($data['serah_ke'] === 'gudang') {

                            // Validasi server-side: checkbox konfirmasi ganda wajib dicentang
                            if (empty($data['konfirmasi_ganda'])) {
                                throw ValidationException::withMessages([
                                    'konfirmasi_ganda' => 'Anda harus menyetujui konfirmasi sebelum melanjutkan.',
                                ]);
                            }

                            try {
                                DB::transaction(function () use ($record) {

                                    $b = $record->barangSetengahJadiHp;

                                    if (! $b) {
                                        throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
                                    }

                                    $panjang = $b->ukuran?->panjang ?? 0;
                                    $lebar = $b->ukuran?->lebar ?? 0;
                                    $tebal = $b->ukuran?->tebal ?? 0;
                                    $kwGrade = $b->grade?->nama_grade ?? '-';

                                    $namaJenisBarang = $b->jenisBarang?->nama_jenis_barang;

                                    $idJenisKayu = JenisKayu::where('nama_kayu', $namaJenisBarang)
                                        ->value('id');

                                    if (! $idJenisKayu) {
                                        throw new \RuntimeException("Jenis kayu \"{$namaJenisBarang}\" tidak ditemukan di master jenis kayu.");
                                    }

                                    $lembar = $record->hasil;
                                    $penyerah = auth()->user()?->name ?? '-';

                                    // Hitung kubikasi (m3). Sesuaikan rumus ini jika berbeda
                                    // dengan rumus yang dipakai di StokPlywoodSiapJualService.
                                    $kubikasi = ($panjang * $lebar * $tebal * $lembar) / 10_000_000_000;

                                    // 1. Catat serah terima dengan tujuan 'gudang'.
                                    // Belum ada fitur "terima" untuk tujuan gudang, jadi
                                    // langsung ditandai diterima oleh pengirim sendiri (auto-terima).
                                    $serahTerima = SerahTerimaGudangSatu::create([
                                        'id_hasil_pilih_plywood' => null,
                                        'id_produksi_terima_gudang_satu' => null,
                                        'id_hasil_terima_gudang_satu' => null,
                                        'id_triplek_mutasi_keluar' => null,
                                        'id_produksi_nyusup' => null,
                                        'id_hasil_nyusup' => $record->id,
                                        'tujuan' => 'gudang',
                                        'diserahkan_oleh' => $penyerah,
                                        'diterima_oleh' => $penyerah,
                                        'status' => 'Diterima',
                                    ]);

                                    // 2. 🔻 KURANGI stok Gudang Satu
                                    app(StokGudangSatuService::class)->kurang(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $panjang,
                                        lebar: $lebar,
                                        tebal: $tebal,
                                        kwGrade: $kwGrade,
                                        lembar: $lembar,
                                        kubikasi: $kubikasi,
                                        keterangan: 'Serah terima dari Nyusup ke Gudang',
                                        referensi: $serahTerima,
                                    );

                                    // 3. 🔺 TAMBAH stok plywood siap jual + catat log
                                    app(StokPlywoodSiapJualService::class)->tambah(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $panjang,
                                        lebar: $lebar,
                                        tebal: $tebal,
                                        kwGrade: $kwGrade,
                                        lembar: $lembar,
                                        keterangan: 'Serah terima dari Nyusup ke Gudang',
                                        referensi: $serahTerima,
                                    );
                                });

                                Notification::make()
                                    ->title('Barang berhasil diserahkan ke Gudang, stok Gudang Satu berkurang, dan stok siap jual bertambah')
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
                    ->hidden(function ($livewire, $record) {
                        // Sembunyikan kalau sudah divalidasi
                        if ($livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi') {
                            return true;
                        }

                        // Sembunyikan kalau sudah DITERIMA (bukan cuma diserahkan)
                        return SerahTerimaGudangSatu::where('id_hasil_nyusup', $record->id)
                            ->where('diterima_oleh', '!=', '-')
                            ->exists();
                    }),

                DeleteAction::make()
                    ->hidden(function ($livewire, $record) {
                        // Sembunyikan kalau sudah divalidasi
                        if ($livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi') {
                            return true;
                        }

                        // Sembunyikan kalau sudah pernah diserahkan (apapun statusnya)
                        return SerahTerimaGudangSatu::where('id_hasil_nyusup', $record->id)->exists();
                    }),
            ])

            /*
            |=====================================================
            | 🧹 BULK ACTIONS
            |=====================================================
            */
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])

            /*
            |=====================================================
            | 📌 DEFAULT GROUP
            |=====================================================
            */
            ->defaultGroup('id_pegawai_nyusup');
    }
}
