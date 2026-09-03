<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Tables;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\PenggunaanLahanRotary;
use App\Services\LogCoreStokService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PenggunaanLahanRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(
                        fn($record) =>
                        "{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}"
                    )
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('lahans', 'penggunaan_lahan_rotaries.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('penggunaan_lahan_rotaries.*');
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('lahan', function ($q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                                ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('Belum Daftar Jenis Kayu'),

                TextColumn::make('jumlah_batang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hpp_average')
                    ->label('HPP Terakhir')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(
                        fn($state) => $state > 0
                            ? 'Rp ' . number_format($state, 0, ',', '.')
                            : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('lahan_selesai')
                    ->label('Selesaikan Lahan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function ($record) {
                        $idProduksi = $record->id_produksi ?? null;
                        if (!$idProduksi) return true;

                        $validated = \App\Models\ValidasiHasilRotary::where('id_produksi', $idProduksi)
                            ->where('status', 'disetujui')
                            ->pluck('role')
                            ->toArray();

                        $kepalaSudah = collect($validated)->contains(
                            fn($role) => str_contains(strtolower($role), 'kepala_produksi')
                        );

                        $pengawasSudah = collect($validated)->contains(
                            fn($role) => str_contains(strtolower($role), 'pengawas_rotary')
                        );

                        return !($kepalaSudah && $pengawasSudah);
                    })
                    ->modalHeading('Konfirmasi Pengosongan Lahan & Stok')
                    ->modalDescription('Periksa rincian stok dan isi penyesuaian hasil sebelum menyelesaikan penggunaan lahan ini.')

                    ->form([
                        Section::make('Informasi Lahan & Stok Saat Ini')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('info_lahan')
                                            ->label('Lahan')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(fn(PenggunaanLahanRotary $record) => "{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}"),

                                        TextInput::make('info_jenis_kayu')
                                            ->label('Jenis Kayu')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(fn(PenggunaanLahanRotary $record) => $record->jenisKayu?->nama_kayu ?? '-'),

                                        TextInput::make('stok_aktif')
                                            ->label('Hasil Kupasan / Total Stok (Batang)')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(function (PenggunaanLahanRotary $record) {
                                                return HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                                    ->where('id_jenis_kayu', $record->id_jenis_kayu)
                                                    ->sum('stok_batang');
                                            }),

                                        TextInput::make('kayu_pecah')
                                            ->label('Kayu Pecah Total (Batang)')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(function (PenggunaanLahanRotary $record) {
                                                return \App\Models\KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($record) {
                                                    $q->where('id_lahan', $record->id_lahan)
                                                        ->where('hpp_average', 0);
                                                })->count();
                                            }),

                                        TextInput::make('akumulasi_total')
                                            ->label('Hasil Real (Batang)')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->default(function (PenggunaanLahanRotary $record) {
                                                $stokTercatat = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                                    ->where('id_jenis_kayu', $record->id_jenis_kayu)
                                                    ->sum('stok_batang');

                                                $totalKayuPecah = \App\Models\KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($record) {
                                                    $q->where('id_lahan', $record->id_lahan)
                                                        ->where('hpp_average', 0);
                                                })->count();

                                                return max(0, $stokTercatat - $totalKayuPecah);
                                            })
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Section::make('Penyesuaian & Catatan Selesai')
                            ->schema([
                                TextInput::make('hasil_sebenarnya')
                                    ->label('Hasil Real Fisik (Batang)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->placeholder('Masukkan hasil real fisik')
                                    ->required()
                                    ->default(function (PenggunaanLahanRotary $record) {
                                        $stokTercatat = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                                            ->where('id_jenis_kayu', $record->id_jenis_kayu)
                                            ->sum('stok_batang');

                                        $totalKayuPecah = \App\Models\KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($record) {
                                            $q->where('id_lahan', $record->id_lahan)
                                                ->where('hpp_average', 0);
                                        })->count();

                                        return max(0, $stokTercatat - $totalKayuPecah);
                                    })
                                    ->helperText('Hasil Real = Hasil Kupasan (Nilai Stok) - Kayu Pecah.'),

                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(3)
                                    ->placeholder('Masukkan catatan jika ada selisih stok atau penyesuaian lapangan...'),
                            ]),
                    ])

                    ->action(function (array $data, PenggunaanLahanRotary $record) {
                        $idLahan     = $record->id_lahan;
                        $idJenisKayu = $record->id_jenis_kayu;

                        if (is_null($idJenisKayu)) {
                            Notification::make()
                                ->title('Gagal: Jenis Kayu Tidak Ditemukan')
                                ->body('Record ini tidak memiliki id_jenis_kayu.')
                                ->danger()
                                ->send();
                            return;
                        }

                        DB::transaction(function () use ($data, $record, $idLahan, $idJenisKayu) {
                            $tglProduksi = $record->produksi_rotary?->tgl_produksi ?? now();

                            $hppTerakhir = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->orderByDesc('id')
                                ->value('hpp_average') ?? 0;

                            // HASIL KUPASAN = Stok Asli (Misal 400)
                            $hasilKupasanStok = (int) HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->sum('stok_batang');

                            $kodeLahan = $record->lahan->kode_lahan ?? 'N/A';
                            $namaLahan = $record->lahan->nama_lahan ?? 'N/A';

                            $hasilReal   = (int) $data['hasil_sebenarnya'];
                            $catatanUser = $data['keterangan'] ?? '-';
                            $userLogin  = Auth::user()?->name ?? 'System';
                            $tglProduksiFmt = \Carbon\Carbon::parse($tglProduksi)->translatedFormat('d F Y');

                            $totalKayuPecah = \App\Models\KayuPecahRotary::whereHas('penggunaanLahan', function ($q) use ($record) {
                                $q->where('id_lahan', $record->id_lahan)
                                    ->where('hpp_average', 0);
                            })->count();

                            // ✅ Format Keterangan yang Diperbarui
                            $keteranganLengkap = sprintf(
                                'SELESAI LAHAN | LAHAN: %s - %s | TGL PROD: %s | HASIL KUPASAN: %d | KAYU PECAH: %d | HASIL REAL: %d | DISELESAIKAN OLEH: %s | CATATAN: %s',
                                $kodeLahan,
                                $namaLahan,
                                $tglProduksiFmt,
                                $hasilKupasanStok,
                                $totalKayuPecah,
                                $hasilReal,
                                $userLogin,
                                $catatanUser
                            );

                            $summariesBerstok = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->get();

                            foreach ($summariesBerstok as $item) {
                                $batangKeluar   = (int)   $item->stok_batang;
                                $kubikasiKeluar = (float) $item->stok_kubikasi;
                                $nilaiKeluar    = (float) $item->nilai_stok;
                                $hppSaatIni     = (float) $item->hpp_average;

                                $log = HppAverageLog::create([
                                    'id_lahan'             => $idLahan,
                                    'id_jenis_kayu'        => $idJenisKayu,
                                    'panjang'              => $item->panjang,
                                    'tanggal'              => $tglProduksi,
                                    'tipe_transaksi'       => 'keluar',
                                    'keterangan'           => $keteranganLengkap,
                                    'referensi_type'       => PenggunaanLahanRotary::class,
                                    'referensi_id'         => $record->id,
                                    'total_batang'         => $batangKeluar,
                                    'total_kubikasi'       => round($kubikasiKeluar, 4),
                                    'harga'                => $hppSaatIni,
                                    'nilai_stok'           => $nilaiKeluar,
                                    'stok_batang_before'   => $batangKeluar,
                                    'stok_kubikasi_before' => round($kubikasiKeluar, 4),
                                    'nilai_stok_before'    => $nilaiKeluar,
                                    'stok_batang_after'    => 0,
                                    'stok_kubikasi_after'  => 0,
                                    'nilai_stok_after'     => 0,
                                    'hpp_average'          => 0,
                                ]);

                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id,
                                ]);

                                if ($batangKeluar > 0) {
                                    app(LogCoreStokService::class)->tambahStok(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $item->panjang,
                                        qty: $batangKeluar,
                                        hargaSatuan: 0,
                                        referensi: $record,
                                        keterangan: $keteranganLengkap,
                                        tanggal: $tglProduksi,
                                    );
                                }
                            }

                            $record->update([
                                'jumlah_batang' => $hasilReal,
                                'hpp_average'   => $hppTerakhir,
                            ]);

                            DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'belum serah',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id_lahan', $idLahan)
                                ->where('tipe', 'lahan_rotary')
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'kubikasi'        => 0,
                                    'status'          => 'Lahan Siap',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);
                        });

                        Notification::make()
                            ->title('✅ Lahan Berhasil Diselesaikan')
                            ->body('Stok direset ke 0. Catatan dan hasil riil telah tersimpan.')
                            ->success()
                            ->send();
                    }),

                EditAction::make()
                    ->visible(
                        fn($record) =>
                        Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                            && !$record->isSelesai()
                    ),

                DeleteAction::make()
                    ->visible(
                        fn($record) =>
                        Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                            && !$record->isSelesai()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false),
                ]),
            ]);
    }
}
