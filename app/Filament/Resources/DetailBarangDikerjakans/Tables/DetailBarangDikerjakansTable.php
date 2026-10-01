<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Tables;

use App\Models\BarangSetengahJadiHp;
use App\Models\DetailBarangDikerjakan;
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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(function ($record) {
                        $serah = SerahTerimaGudangSatu::where('id_hasil_nyusup', $record->id)->first();

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        return $serah->diterima_oleh === '-' ? 'Menunggu Diterima' : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = SerahTerimaGudangSatu::where('id_hasil_nyusup', $record->id)->first();

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),
            ])

            /*
            |=====================================================
            | ➕ HEADER ACTIONS
            |=====================================================
            */
            ->headerActions([

                                // 🌟 Tombol pengembalian sisa bahan nyusup ke Gudang Satu.
                //
                // Barang yang dikembalikan dipilih dari PALET MODAL
                // (SerahTerimaGudangSatu dengan tujuan='nyusup') — sumber
                // yang sama persis dipakai Select "Pilih Palet Modal" di
                // form Create/Edit Detail Barang Dikerjakan. Sisa dihitung
                // oleh accessor SerahTerimaGudangSatu::sisa (qtyAsli -
                // total modal terpakai - jumlah_dikembalikan), persis pola
                // ledger per-palet yang dipakai Hotpress.
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
                            Select::make('id_serah_terima_gudang_satu')
                    ->label('Bahan')
                    ->required()
                    ->live()
                    ->searchable()
                    ->helperText(
                        'Pilih bahan yang masih memiliki sisa untuk dikembalikan ke Gudang Satu.'
                    )
                    ->options(function () use ($ownerId) {
                        if (! $ownerId) {
                            return [];
                        }

                        // Ambil palet modal yang benar-benar dipakai di production ini saja
                        $idSerahTerimaTerpakai = DetailBarangDikerjakan::query()
                            ->whereHas('pegawaiNyusup', function ($q) use ($ownerId) {
                                $q->where('id_produksi_nyusup', $ownerId);
                            })
                            ->whereNotNull('id_serah_terima_gudang_satu')
                            ->pluck('id_serah_terima_gudang_satu')
                            ->unique();

                        if ($idSerahTerimaTerpakai->isEmpty()) {
                            return [];
                        }

                        return SerahTerimaGudangSatu::query()
                            ->whereIn('id', $idSerahTerimaTerpakai)
                            ->where('diterima_oleh', '!=', '-')
                            ->where('tujuan', 'nyusup')
                            ->with([
                                'hasilPilihPlywood.barangSetengahJadiHp',
                                'hasilTerimaGudangSatu',
                                'hasilNyusup',
                                'triplekMutasiKeluar',
                            ])
                            ->get()
                            ->filter(fn ($item) => $item->sisa > 0)
                            ->mapWithKeys(function ($item) {
                                $sisa = rtrim(rtrim(number_format($item->sisa, 2, '.', ''), '0'), '.');

                                $b = $item->barangSetengahJadi;
                                $ukuran = $b?->ukuran?->nama_ukuran ?? '-';
                                $grade  = $b?->grade?->nama_grade ?? '-';
                                $jenis  = $b?->jenisBarang?->nama_jenis_barang ?? '-';
                                $noPalet = $item->hasilNyusup?->no_palet ?? '-';

                                return [
                                    $item->id =>
                                        "Palet {$noPalet} | {$jenis} | {$ukuran} | Grade {$grade} | Sisa {$sisa} Lbr",
                                ];
                            })
                            ->toArray();
                        })
                ->afterStateUpdated(
                    function ($state, callable $set) {
                        if (! $state) {
                            $set('maks_pengembalian', null);
                            return;
                        }
                        $serah = SerahTerimaGudangSatu::find($state);
                        $set('maks_pengembalian', $serah?->sisa ?? 0);
                    }
                ),

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
                                    $get('maks_pengembalian')
                                    ?? 0
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

            $produksi =
                $livewire->ownerRecord;

            app(TerimaGudangSatuService::class)
                ->kembaliDariNyusup($serah, $jumlah);

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
                                    'gudang' => 'Serah ke Gudang Plywood Siap Jual',
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
