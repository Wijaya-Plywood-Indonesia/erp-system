<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\RelationManagers;

use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use App\Models\SerahTerimaTriplekJadi;
use App\Models\StokTriplekJadi;
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

    /**
     * Sumber yang HASIL nya sudah pernah tercatat/dihitung sebelumnya
     * (barang cacat yang diperbaiki) — jadi ketika diterima di Pilih Plywood,
     * TIDAK boleh menambah stok lagi (mencegah double count).
     */
    protected const SUMBER_TANPA_STOK = ['dempul', 'tembel_triplek'];

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
                        'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
                        'detailDempul.barangSetengahJadi.ukuran',
                        'detailDempul.barangSetengahJadi.grade',
                        'detailDempul.barangSetengahJadi.jenisBarang',
                        'hasilTembelTriplek.serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                        'hasilTembelTriplek.serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.grade',
                        'hasilTembelTriplek.serahTerimaTriplekCacat.hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                    ])
                    ->where('diterima_oleh', '-')
                    ->orWhere('id_produksi_pilih_plywood', $ownerId)
                    ->orderBy('diterima_oleh', 'asc')
                    ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->state(fn ($record) => $record->hasil?->no_palet ?? $record->hasil?->nomor_palet ?? '-')
                    ->badge()
                    ->color('info'),

                TextColumn::make('asal_label')
                    ->label('Asal')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Sanding' => 'warning',
                        'Graji Triplek' => 'danger',
                        'Dempul (Perbaikan)' => 'gray',
                        'Tembel Triplek (Perbaikan)' => 'gray',
                        default => 'gray',
                    }),

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

                TextColumn::make('pengaruh_stok')
                    ->label('Pengaruh Stok')
                    ->badge()
                    ->state(fn ($record) => in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                        ? 'Tidak Menambah Stok'
                        : 'Menambah Stok')
                    ->color(fn ($record) => in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                        ? 'gray'
                        : 'success'),

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
                    ->modalHeading(fn ($record) => in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                        ? 'Terima Barang Perbaikan?'
                        : 'Terima Triplek Jadi?')
                    ->modalDescription(fn ($record) => in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                        ? 'Barang hasil perbaikan ini sudah pernah tercatat sebelumnya sebagai barang cacat, sehingga penerimaan ini HANYA menandai status selesai dan TIDAK menambah stok (mencegah dobel hitung).'
                        : 'Barang yang diterima akan memotong dan langsung masuk ke Stok Triplek Jadi.')
                    ->visible(fn ($record) => $record->diterima_oleh === '-')
                    ->schema(function ($record) {
                        return [
                            Grid::make(2)->schema([
                                Placeholder::make('preview_asal')
                                    ->label('Dari')
                                    ->content($record->asal_label),
                                Placeholder::make('preview_qty')
                                    ->label('Kuantitas')
                                    ->content($record->jumlah.' Lembar'),
                                Placeholder::make('preview_grade')
                                    ->label('Grade')
                                    ->content($record->barang_setengah_jadi?->grade?->nama_grade ?? '-'),
                                Placeholder::make('preview_ukuran')
                                    ->label('Ukuran')
                                    ->content($record->barang_setengah_jadi?->ukuran?->nama_ukuran ?? '-'),
                                Placeholder::make('preview_pengaruh_stok')
                                    ->label('Pengaruh Stok')
                                    ->content(in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                                        ? 'Tidak menambah stok (barang perbaikan)'
                                        : 'Menambah stok'),
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

                                // 1. Update status Serah Terima — SELALU dilakukan, apapun sumbernya
                                $fresh->update([
                                    'diterima_oleh' => Auth::user()->name.' - Pilih Plywood',
                                    'id_produksi_pilih_plywood' => $ownerId,
                                    'status' => 'Terima Triplek',
                                ]);

                                // 2. Tambah ke stok — HANYA untuk sumber Sanding & Graji Triplek.
                                // Dempul & Tembel Triplek adalah barang cacat yang sudah pernah
                                // tercatat sebelumnya, jadi diterima-nya di sini murni administratif
                                // (menutup antrian), tidak boleh menambah stok lagi.
                                if (! in_array($fresh->tipe_sumber, self::SUMBER_TANPA_STOK, true)) {
                                    $this->tambahStokTriplek($fresh);
                                }
                            });

                            Notification::make()
                                ->title('Barang Berhasil Diterima')
                                ->body(in_array($record->tipe_sumber, self::SUMBER_TANPA_STOK, true)
                                    ? 'Status diperbarui. Stok tidak berubah (barang perbaikan).'
                                    : 'Barang masuk ke Stok Triplek Jadi.')
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
     * Triplek Jadi beserta Log HPP nya.
     *
     * PENTING: hanya dipanggil untuk sumber Sanding & Graji Triplek.
     * Jangan panggil untuk sumber Dempul/Tembel Triplek (lihat SUMBER_TANPA_STOK).
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
            'tipe_transaksi' => 'Masuk',
            'keterangan' => $keteranganLog,
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
    }
}
