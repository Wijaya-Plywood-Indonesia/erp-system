<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Tables;

use App\Models\BahanTerimaGudangSatu;
use App\Models\DetailBarangDikerjakan;
use App\Models\HppPlywoodSiapJualLog;
use App\Models\JenisKayu;
use App\Models\StokPlywoodSiapJual;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetailBarangDikerjakansTable
{
    public const KONVERSI_KUBIKASI = 10_000_000;

    /**
     * Ambil objek "barang setengah jadi" dari record, dengan fallback:
     * 1. Relasi langsung barangSetengahJadiHp (jalur lama / manual)
     * 2. Relasi serahTerima->barangSetengahJadi (jalur baru, dari Serah Terima Gudang Satu,
     *    yang mana itu sendiri bisa bersumber dari HasilPilihPlywood ATAU HasilTerimaGudangSatu)
     *
     * Mengembalikan objek yang minimal punya accessor/relasi: grade, ukuran, jenisBarang.
     */
    private static function resolveBarang($record)
    {
        return $record->barangSetengahJadiHp
            ?? $record->serahTerima?->barangSetengahJadi;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(
                        fn ($record) => $record->pegawaiNyusup?->pegawai?->nama_pegawai
                            ?? 'Pegawai Tidak Diketahui'
                    )
                    ->collapsible(true),
            ])

            ->columns([

                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = self::resolveBarang($record);

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
                        return $query->where(function (Builder $outer) use ($search) {
                            // Sumber 1: relasi langsung barangSetengahJadiHp
                            $outer->whereHas('barangSetengahJadiHp', function (Builder $q) use ($search) {
                                $q->whereHas('ukuran', function ($qu) use ($search) {
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
                            })
                            // Sumber 2: via SerahTerimaGudangSatu -> HasilPilihPlywood
                                ->orWhereHas('serahTerima.hasilPilihPlywood', function (Builder $q) use ($search) {
                                    $q->whereHas('barangSetengahJadiHp.ukuran', function ($qu) use ($search) {
                                        $qu->where('panjang', 'like', "%{$search}%")
                                            ->orWhere('lebar', 'like', "%{$search}%")
                                            ->orWhere('tebal', 'like', "%{$search}%")
                                            ->orWhereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                                    })
                                        ->orWhereHas('barangSetengahJadiHp.grade', function ($qg) use ($search) {
                                            $qg->where('nama_grade', 'like', "%{$search}%")
                                                ->orWhereHas('kategoriBarang', fn ($qk) => $qk->where('nama_kategori', 'like', "%{$search}%"));
                                        })
                                        ->orWhereHas('barangSetengahJadiHp.jenisBarang', fn ($qj) => $qj->where('nama_jenis_barang', 'like', "%{$search}%"));
                                })
                            // Sumber 3: via SerahTerimaGudangSatu -> HasilTerimaGudangSatu (grade/ukuran/jenis langsung)
                                ->orWhereHas('serahTerima.hasilTerimaGudangSatu', function (Builder $q) use ($search) {
                                    $q->whereHas('ukuran', function ($qu) use ($search) {
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
                TextColumn::make('diserahkan_at')
                    ->label('Penyerahan')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if (is_null($record->diserahkan_at)) {
                            return 'Draft';
                        }
                        $waktu = $record->diserahkan_at->format('d M Y H:i');

                        return "Diserahkan ({$waktu})";
                    })
                    ->color(fn ($state) => str_contains($state, 'Diserahkan') ? 'success' : 'gray')
                    ->description(function ($record) {
                        if ($record->diserahkan_by) {
                            return "Oleh: {$record->diserahkan_by}";
                        }

                        return null;
                    }),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                Action::make('serah_hasil')
                    ->label('SERAH')
                    ->button()
                    ->color('warning')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Penyerahan Hasil')
                    ->modalDescription('Apakah Anda yakin ingin menyerahkan hasil pengerjaan barang ini ke Gudang/Sanding? Data yang sudah diserahkan akan masuk ke antrean mutasi masuk.')
                    ->visible(function ($record, $livewire) {
                        $isDivalidasi = $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';

                        return ! $isDivalidasi && is_null($record->diserahkan_at);
                    })
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            // Fallback: baca barang setengah jadi baik dari relasi langsung
                            // maupun dari serah terima gudang satu (2 kemungkinan sumber di sana).
                            $b = self::resolveBarang($record);
                            $ukuran = $b?->ukuran;

                            if (! $b || ! $ukuran) {
                                throw new Exception('Spesifikasi barang setengah jadi atau dimensi ukuran tidak ditemukan.');
                            }

                            $namaBarangLengkap = $b->jenisBarang?->nama_jenis_barang;

                            if (! $namaBarangLengkap) {
                                throw new Exception('Nama jenis barang tidak ditemukan pada data barang setengah jadi.');
                            }

                            // Validasi sisa, hanya berlaku kalau record ini terkait Serah Terima Gudang Satu.
                            if ($record->id_serah_terima_gudang_satu) {
                                $serahTerima = $record->serahTerima()->lockForUpdate()->first();

                                if (! $serahTerima) {
                                    throw new Exception('Data serah terima gudang satu terkait tidak ditemukan.');
                                }

                                // Hitung sisa TANPA menghitung modal record ini sendiri,
                                // supaya tidak dobel-kurang kalau modal sudah tersimpan sebelumnya.
                                $terpakaiNyusupLain = DetailBarangDikerjakan::where('id_serah_terima_gudang_satu', $serahTerima->id)
                                    ->where('id', '!=', $record->id)
                                    ->sum('modal');

                                $terpakaiBahan = BahanTerimaGudangSatu::where('id_serah_terima_gudang_satu', $serahTerima->id)
                                    ->sum('jumlah');

                                $sisaSebenarnya = (float) $serahTerima->qty_asli
                                    - (float) $terpakaiBahan
                                    - (float) $terpakaiNyusupLain;

                                if ((float) $record->modal > $sisaSebenarnya) {
                                    throw new Exception(
                                        "Modal ({$record->modal}) melebihi sisa stok pada Serah Terima Gudang Satu (sisa: {$sisaSebenarnya})."
                                    );
                                }
                            }

                            // Cari jenis kayu langsung lewat query SQL (lebih efisien dari all()->first())
                            // Diurutkan dari nama terpanjang agar "Meranti Merah" tidak salah tertangkap
                            // sebagai "Meranti" saja.
                            $jenisKayuReal = JenisKayu::query()
                                ->orderByRaw('LENGTH(nama_kayu) DESC')
                                ->get()
                                ->first(fn ($kayu) => str_contains(
                                    strtolower($namaBarangLengkap),
                                    strtolower($kayu->nama_kayu)
                                ));

                            if (! $jenisKayuReal) {
                                throw new Exception(
                                    "Gagal mendeteksi nama kayu dari produk '{$namaBarangLengkap}'. ".
                                        'Pastikan teks produk mengandung kata yang cocok dengan master tabel jenis_kayus (seperti Sengon, Meranti, dll).'
                                );
                            }

                            $idJenisKayu = $jenisKayuReal->id;

                            $kwGrade = $b->grade?->nama_grade;
                            if (! $kwGrade) {
                                throw new Exception('Grade barang tidak ditemukan, tidak bisa memproses penyerahan.');
                            }

                            $panjang = (float) $ukuran->panjang;
                            $lebar = (float) $ukuran->lebar;
                            $tebal = (float) $ukuran->tebal;
                            $qty = (int) $record->hasil;

                            // Konstanta konversi volume: (mm x mm x mm) ke m3, disesuaikan skala tabel ukuran.
                            $kubikasiBaru = ($panjang * $lebar * $tebal * $qty) / self::KONVERSI_KUBIKASI;

                            $namaUser = Auth::user()->name;

                            // Baru update record setelah semua validasi lolos
                            $record->update([
                                'diserahkan_at' => now(),
                                'diserahkan_by' => $namaUser,
                            ]);

                            $stok = StokPlywoodSiapJual::where('id_jenis_kayu', $idJenisKayu)
                                ->where('panjang', $panjang)
                                ->where('lebar', $lebar)
                                ->where('tebal', $tebal)
                                ->where('kw_grade', $kwGrade)
                                ->lockForUpdate()
                                ->first();

                            if (! $stok) {
                                $stok = StokPlywoodSiapJual::create([
                                    'id_jenis_kayu' => $idJenisKayu,
                                    'panjang' => $panjang,
                                    'lebar' => $lebar,
                                    'tebal' => $tebal,
                                    'kw_grade' => $kwGrade,
                                    'stok_lembar' => 0,
                                    'stok_kubikasi' => 0,
                                ]);
                            }

                            $stokLembarBefore = (int) $stok->stok_lembar;
                            $stokKubikasiBefore = (float) $stok->stok_kubikasi;
                            $stokLembarAfter = $stokLembarBefore + $qty;
                            $stokKubikasiAfter = round($stokKubikasiBefore + $kubikasiBaru, 6);

                            $log = HppPlywoodSiapJualLog::create([
                                'id_jenis_kayu' => $idJenisKayu,
                                'panjang' => $panjang,
                                'lebar' => $lebar,
                                'tebal' => $tebal,
                                'kw_grade' => $kwGrade,
                                'tanggal' => now(),
                                'tipe_transaksi' => 'masuk',
                                'referensi_type' => get_class($record),
                                'referensi_id' => $record->id,
                                'total_lembar' => $qty,
                                'total_kubikasi' => $kubikasiBaru,
                                'stok_lembar_before' => $stokLembarBefore,
                                'stok_kubikasi_before' => $stokKubikasiBefore,
                                'stok_lembar_after' => $stokLembarAfter,
                                'stok_kubikasi_after' => $stokKubikasiAfter,
                                'keterangan' => "Terima Hasil Pengerjaan Nyusup | Oleh: {$namaUser}",
                            ]);

                            $stok->update([
                                'stok_lembar' => $stokLembarAfter,
                                'stok_kubikasi' => $stokKubikasiAfter,
                                'id_last_log' => $log->id,
                            ]);
                        });

                        Notification::make()
                            ->success()
                            ->title('Hasil Kerja Berhasil Diserahkan')
                            ->body('Log mutasi masuk tercatat & Saldo stok siap jual otomatis bertambah.')
                            ->send();
                    }),

                EditAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultGroup('id_pegawai_nyusup');
    }
}
