<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Tables;

use App\Models\HppPlywoodSiapJualLog;
use App\Models\JenisKayu;
use App\Models\StokPlywoodSiapJual;
use Exception;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DetailBarangDikerjakansTable
{
    public const KONVERSI_KUBIKASI = 10_000_000;
    public static function configure(Table $table): Table
    {
        return $table
            ->groups([
                Group::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->getTitleFromRecordUsing(
                        fn($record) =>
                        $record->pegawaiNyusup?->pegawai?->nama_pegawai
                            ?? 'Pegawai Tidak Diketahui'
                    )
                    ->collapsible(true),
            ])

            ->columns([

                TextColumn::make('barang')
                    ->label('Barang')
                    ->getStateUsing(function ($record) {
                        $b = $record->barangSetengahJadiHp;

                        if (! $b) {
                            return '-';
                        }

                        $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran   = $b->ukuran?->nama_ukuran ?? '-';
                        $grade    = $b->grade?->nama_grade ?? '-';
                        $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

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
                                        ->orWhereHas('kategoriBarang', fn($qk) => $qk->where('nama_kategori', 'like', "%{$search}%"));
                                })
                                ->orWhereHas('jenisBarang', fn($qj) => $qj->where('nama_jenis_barang', 'like', "%{$search}%"));
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
                    ->color(fn($state) => str_contains($state, 'Diserahkan') ? 'success' : 'gray')
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
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
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
                            $b = $record->barangSetengahJadiHp;
                            $ukuran = $b?->ukuran;

                            if (!$b || !$ukuran) {
                                throw new Exception('Spesifikasi barang setengah jadi atau dimensi ukuran tidak ditemukan.');
                            }

                            $namaBarangLengkap = $b->jenisBarang?->nama_jenis_barang;

                            if (!$namaBarangLengkap) {
                                throw new Exception('Nama jenis barang tidak ditemukan pada data barang setengah jadi.');
                            }

                            // Cari jenis kayu langsung lewat query SQL (lebih efisien dari all()->first())
                            // Diurutkan dari nama terpanjang agar "Meranti Merah" tidak salah tertangkap
                            // sebagai "Meranti" saja.
                            $jenisKayuReal = JenisKayu::query()
                                ->orderByRaw('LENGTH(nama_kayu) DESC')
                                ->get()
                                ->first(fn($kayu) => str_contains(
                                    strtolower($namaBarangLengkap),
                                    strtolower($kayu->nama_kayu)
                                ));

                            if (!$jenisKayuReal) {
                                throw new Exception(
                                    "Gagal mendeteksi nama kayu dari produk '{$namaBarangLengkap}'. " .
                                        "Pastikan teks produk mengandung kata yang cocok dengan master tabel jenis_kayus (seperti Sengon, Meranti, dll)."
                                );
                            }

                            $idJenisKayu = $jenisKayuReal->id;

                            $kwGrade = $b->grade?->nama_grade;
                            if (!$kwGrade) {
                                throw new Exception('Grade barang tidak ditemukan, tidak bisa memproses penyerahan.');
                            }

                            $panjang = (float) $ukuran->panjang;
                            $lebar   = (float) $ukuran->lebar;
                            $tebal   = (float) $ukuran->tebal;
                            $qty     = (int) $record->hasil;

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

                            if (!$stok) {
                                $stok = StokPlywoodSiapJual::create([
                                    'id_jenis_kayu' => $idJenisKayu,
                                    'panjang'       => $panjang,
                                    'lebar'         => $lebar,
                                    'tebal'         => $tebal,
                                    'kw_grade'      => $kwGrade,
                                    'stok_lembar'   => 0,
                                    'stok_kubikasi' => 0,
                                ]);
                            }

                            $stokLembarBefore   = (int) $stok->stok_lembar;
                            $stokKubikasiBefore = (float) $stok->stok_kubikasi;
                            $stokLembarAfter    = $stokLembarBefore + $qty;
                            $stokKubikasiAfter  = round($stokKubikasiBefore + $kubikasiBaru, 6);

                            $log = HppPlywoodSiapJualLog::create([
                                'id_jenis_kayu'        => $idJenisKayu,
                                'panjang'              => $panjang,
                                'lebar'                => $lebar,
                                'tebal'                => $tebal,
                                'kw_grade'             => $kwGrade,
                                'tanggal'              => now(),
                                'tipe_transaksi'       => 'masuk',
                                'referensi_type'       => get_class($record),
                                'referensi_id'         => $record->id,
                                'total_lembar'         => $qty,
                                'total_kubikasi'       => $kubikasiBaru,
                                'stok_lembar_before'   => $stokLembarBefore,
                                'stok_kubikasi_before' => $stokKubikasiBefore,
                                'stok_lembar_after'    => $stokLembarAfter,
                                'stok_kubikasi_after'  => $stokKubikasiAfter,
                                'keterangan'           => "Terima Hasil Pengerjaan Nyusup | Oleh: {$namaUser}",
                            ]);

                            $stok->update([
                                'stok_lembar'   => $stokLembarAfter,
                                'stok_kubikasi' => $stokKubikasiAfter,
                                'id_last_log'   => $log->id,
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
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
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
