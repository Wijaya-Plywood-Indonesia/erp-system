<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Models\HppTriplekJadiLog;
use App\Models\HppTriplekMthLog;
use App\Models\JenisKayu;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\StokTriplekJadi;
use App\Models\StokTriplekMth;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SerahTerimaTriplekJadiRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerimaTriplekJadi';

    protected static ?string $title = 'Terima Triplek Jadi';

    public function table(Table $table): Table
    {
        $ownerId = $this->getOwnerRecord()->id;

        return $table
            ->modifyQueryUsing(function ($query) use ($ownerId) {
                // Hapus binding where bawaan Filament agar bisa lihat yang belum diterima
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query
                    ->with([
                        'hasilSanding.barangSetengahJadi.ukuran',
                        'hasilSanding.barangSetengahJadi.grade',
                        'hasilSanding.barangSetengahJadi.jenisBarang',
                        'hasilGrajiTriplek.barangSetengahJadiHp.ukuran', // Sesuaikan jika ada Graji
                    ])
                    ->where('diterima_oleh', '-')
                    ->orWhere('id_produksi_pilih_plywood', $ownerId)
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->state(fn ($record) => $record->hasil?->no_palet ?? '-')
                    ->badge()
                    ->color('info'),

                TextColumn::make('asal_label')
                    ->label('Asal Mesin')
                    ->badge()
                    ->color(fn ($state) => $state === 'Sanding' ? 'warning' : 'danger'),

                TextColumn::make('jenis_barang')
                    ->label('Jenis Barang')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->jenisBarang?->nama_jenis_barang ?? '-'),

                TextColumn::make('grade')
                    ->label('Grade')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->grade?->nama_grade ?? '-'),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(fn ($record) => $record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-'),

                TextColumn::make('jumlah')
                    ->label('Qty (Lembar)')
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('diterima_oleh')
                    ->label('Status Diterima')
                    ->badge()
                    ->color(fn ($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn ($state) => $state === '-' ? 'Menunggu' : $state),
                    
                TextColumn::make('created_at')
                    ->label('Waktu Serah')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima ke Stok')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Terima Triplek Jadi?')
                    ->modalDescription('Barang yang diterima akan memotong dan langsung masuk ke Stok Triplek Jadi.')
                    ->visible(fn ($record) => $record->diterima_oleh === '-')
                    ->schema(function ($record) {
                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('preview_asal')
                                    ->label('Dari Mesin')
                                    ->content($record->asal_label),
                                Placeholder::make('preview_qty')
                                    ->label('Kuantitas')
                                    ->content($record->jumlah . ' Lembar'),
                                Placeholder::make('preview_grade')
                                    ->label('Grade')
                                    ->content($record->barang_setengah_jadi?->grade?->nama_grade ?? '-'),
                                Placeholder::make('preview_ukuran')
                                    ->label('Ukuran')
                                    ->content($record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-'),
                            ]),
                        ];
                    })
                    ->action(function ($record) use ($ownerId) {
                        try {
                            DB::transaction(function () use ($record, $ownerId) {
                                $fresh = SerahTerimaTriplekJadi::lockForUpdate()->find($record->id);

                                if (! $fresh || $fresh->diterima_oleh !== '-') {
                                    throw new \RuntimeException('Barang ini sudah diterima pengawas lain.');
                                }

                                // 1. Update status Serah Terima
                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name . ' - Pilih Plywood',
                                    'id_produksi_pilih_plywood' => $ownerId,
                                    'status' => 'Terima Triplek',
                                ]);

                                // 2. Tambah ke stok (fungsi ada di bawah)
                                $this->tambahStokTriplek($fresh);
                            });

                            Notification::make()
                                ->title('Barang Berhasil Diterima dan Masuk Stok')
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal Menerima Barang')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    /**
     * Memotong proses gudang dengan menambahkannya langsung ke Stok 
     * Triplek Jadi beserta Log HPP nya, sekaligus mengurangi Stok Triplek Mentah.
     */
    protected function tambahStokTriplek(SerahTerimaTriplekJadi $serahTerima): void
    {
        $bsj = $serahTerima->barang_setengah_jadi; // Memanggil Accessor dari Model Anda

        if (! $bsj) {
            throw new \RuntimeException('Data barang setengah jadi tidak ditemukan.');
        }

        $ukuran = $bsj->ukuran;
        $grade = $bsj->grade;
        $jenisBarang = $bsj->jenisBarang;

        if (! $ukuran || ! $grade || ! $jenisBarang) {
            throw new \RuntimeException('Data ukuran, grade, atau jenis barang tidak lengkap.');
        }

        $jenisKayu = JenisKayu::where('nama_kayu', $jenisBarang->nama_jenis_barang)->first();

        if (! $jenisKayu) {
            throw new \RuntimeException("Jenis kayu \"{$jenisBarang->nama_jenis_barang}\" tidak ditemukan di tabel Jenis Kayu. Samakan namanya terlebih dahulu.");
        }

        $lembar = (float) $serahTerima->qty_asli;
        $kubikasi = ($lembar * (float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal) / 10000000;

        $stok = StokTriplekJadi::firstOrCreate(
            [
                'id_jenis_kayu' => $jenisKayu->id,
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

        // Penambahan Nilai
        $stok->stok_lembar += $lembar;
        $stok->stok_kubikasi += $kubikasi;
        $stok->save();

        $tanggalProduksi = date('d/m/Y', strtotime($this->getOwnerRecord()->tanggal_produksi));
        
        // Ambil nama pengawas yang sedang login dan menekan tombol "Terima"
        $namaPenerima = Auth::user()->name;

        $keteranganLog = "Terima dari {$serahTerima->asal_label}, diterima oleh {$namaPenerima} untuk produksi tgl {$tanggalProduksi}";

        // Mencatat history/log
        $log = HppTriplekJadiLog::create([
            'id_jenis_kayu' => $jenisKayu->id,
            'panjang' => $ukuran->panjang,
            'lebar' => $ukuran->lebar,
            'tebal' => $ukuran->tebal,
            'kw_grade' => $grade->nama_grade,
            'tanggal' => now()->toDateString(),
            'tipe_transaksi' => 'Masuk', // Sesuai kesepakatan agar terbaca positif di UI Anda
            'keterangan' => $keteranganLog, // Masukkan variabel keterangan di sini
            'referensi_type' => SerahTerimaTriplekJadi::class,
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
        
        // Simpan id log terakhir ke tabel stok jika kolomnya ada
        $stok->update(['id_last_log' => $log->id]);

        // ── KURANGI STOK TRIPLEK MENTAH (boleh minus, crosscheck) ──
        $this->kurangiStokTriplekMth($jenisKayu, $ukuran, $grade->nama_grade, $lembar, $serahTerima);
    }

    /**
     * Kurangi Stok Triplek Mentah sesuai barang & qty yang diterima.
     * Boleh minus (crosscheck).
     */
    protected function kurangiStokTriplekMth($jenisKayu, $ukuran, string $kwGrade, float $lembar, SerahTerimaTriplekJadi $serahTerima): void
    {
        $stokMth = StokTriplekMth::where('id_jenis_kayu', $jenisKayu->id)
            ->where('panjang', $ukuran->panjang)
            ->where('lebar', $ukuran->lebar)
            ->where('tebal', $ukuran->tebal)
            ->where('kw_grade', $kwGrade)
            ->lockForUpdate()
            ->first();

        if (! $stokMth) {
            $stokMth = StokTriplekMth::create([
                'id_jenis_kayu' => $jenisKayu->id,
                'panjang'       => $ukuran->panjang,
                'lebar'         => $ukuran->lebar,
                'tebal'         => $ukuran->tebal,
                'kw_grade'      => $kwGrade,
                'stok_lembar'   => 0,
                'stok_kubikasi' => 0,
                'nilai_stok'    => 0,
                'hpp_average'   => 0,
            ]);
        }

        $kubikasi = ($lembar * (float) $ukuran->panjang * (float) $ukuran->lebar * (float) $ukuran->tebal) / 10000000;

        $stokLembarBefore   = (float) $stokMth->stok_lembar;
        $stokKubikasiBefore = (float) $stokMth->stok_kubikasi;
        $nilaiStokBefore    = (float) $stokMth->nilai_stok;

        $stokMth->stok_lembar   = $stokLembarBefore - $lembar;
        $stokMth->stok_kubikasi = round($stokKubikasiBefore - $kubikasi, 6);
        $stokMth->save();

        $namaPenerima    = Auth::user()->name;
        $tanggalProduksi = date('d/m/Y', strtotime($this->getOwnerRecord()->tanggal_produksi));
        $keperluan       = 'Sanding';

        $log = HppTriplekMthLog::create([
            'id_jenis_kayu'        => $jenisKayu->id,
            'panjang'              => $ukuran->panjang,
            'lebar'                => $ukuran->lebar,
            'tebal'                => $ukuran->tebal,
            'kw_grade'             => $kwGrade,
            'tanggal'              => now()->toDateString(),
            'tipe_transaksi'       => 'Keluar',
            'keterangan'           => "Dipakai produksi: {$keperluan} - Palet {$serahTerima->hasil?->no_palet} (oleh {$namaPenerima})",
            'referensi_type'       => SerahTerimaTriplekJadi::class,
            'referensi_id'         => $serahTerima->id,
            'total_lembar'         => $lembar,
            'total_kubikasi'       => $kubikasi,
            'hpp_pekerja'          => 0,
            'hpp_bahan_penolong'   => 0,
            'hpp_average'          => $stokMth->hpp_average,
            'nilai_stok'           => $nilaiStokBefore,
            'stok_lembar_before'   => $stokLembarBefore,
            'stok_kubikasi_before' => $stokKubikasiBefore,
            'nilai_stok_before'    => $nilaiStokBefore,
            'stok_lembar_after'    => $stokMth->stok_lembar,
            'stok_kubikasi_after'  => $stokMth->stok_kubikasi,
            'nilai_stok_after'     => $nilaiStokBefore,
        ]);

        $stokMth->update(['id_last_log' => $log->id]);
    }
}