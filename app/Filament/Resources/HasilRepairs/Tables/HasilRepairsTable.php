<?php

namespace App\Filament\Resources\HasilRepairs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

use App\Models\RencanaRepair;
use App\Models\HasilRepair;

class HasilRepairsTable
{
    /**
     * Helper: Mengambil ID Rencana KHUSUS untuk ID Produksi ini saja.
     */
    protected static function getGroupRencanaIds($record, $idProduksiRepair)
    {
        return RencanaRepair::query()
            ->join('rencana_pegawais', 'rencana_pegawais.id', '=', 'rencana_repairs.id_rencana_pegawai')
            ->where('rencana_repairs.id_produksi_repair', $idProduksiRepair)
            ->where('rencana_repairs.id_modal_repair', $record->id_modal_repair)
            ->where('rencana_repairs.kw', $record->kw)
            ->where('rencana_pegawais.nomor_meja', $record->nomor_meja)
            ->pluck('rencana_repairs.id')
            ->toArray();
    }

    public static function configure(Table $table, $idProduksiRepair, $tanggalProduksi): Table
    {
        return $table
            ->heading('Hasil Repairs - ' . \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y'))
            ->query(function () use ($idProduksiRepair) {
                return RencanaRepair::query()
                    ->select([
                        'rencana_repairs.id_modal_repair',
                        'rencana_repairs.kw',
                        'rencana_repairs.id_produksi_repair',
                        'rencana_pegawais.nomor_meja',
                        'produksi_repairs.tanggal',
                        DB::raw('MIN(rencana_repairs.id) as id'),
                        DB::raw('COUNT(DISTINCT rencana_repairs.id) as jumlah_pekerja'),
                        DB::raw('SUM(COALESCE(hasil_repairs.jumlah, 0)) as total_hasil'),
                        DB::raw('MAX(hasil_repairs.keterangan) as keterangan'),
                        DB::raw('GROUP_CONCAT(DISTINCT pegawais.nama_pegawai ORDER BY pegawais.nama_pegawai ASC SEPARATOR ", ") as list_pegawai')
                    ])
                    ->join('rencana_pegawais', 'rencana_pegawais.id', '=', 'rencana_repairs.id_rencana_pegawai')
                    ->join('pegawais', 'pegawais.id', '=', 'rencana_pegawais.id_pegawai')
                    ->join('produksi_repairs', 'produksi_repairs.id', '=', 'rencana_repairs.id_produksi_repair')
                    ->leftJoin('hasil_repairs', 'hasil_repairs.id_rencana_repair', '=', 'rencana_repairs.id')
                    ->where('rencana_repairs.id_produksi_repair', $idProduksiRepair)
                    ->groupBy([
                        'rencana_repairs.id_modal_repair',
                        'rencana_repairs.kw',
                        'rencana_repairs.id_produksi_repair',
                        'rencana_pegawais.nomor_meja',
                        'produksi_repairs.tanggal'
                    ])
                    ->orderBy('rencana_pegawais.nomor_meja', 'asc');
            })

            ->columns([
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->state(function ($record) {
                        $rencana = RencanaRepair::with('modalRepairs.ukuran')->find($record->id);
                        return $rencana?->modalRepairs?->ukuran?->dimensi ?? '-';
                    })
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('jenis_kayu')
                    ->label('Jenis Kayu')
                    ->state(function ($record) {
                        $rencana = RencanaRepair::with('modalRepairs.jenisKayu')->find($record->id);
                        return $rencana?->modalRepairs?->jenisKayu?->nama_kayu ?? '-';
                    })
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('kw')
                    ->label('KW')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('nomor_meja')
                    ->label('Meja')
                    ->formatStateUsing(fn($state) => "Meja {$state}")
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('pegawai')
                    ->label('Pegawai')
                    ->wrap()
                    ->sortable(false)
                    ->state(fn($record) => $record->list_pegawai ?? '-'),

                TextColumn::make('total_hasil')
                    ->label('Hasil Produksi')
                    ->default(0)
                    ->numeric()
                    ->suffix(' lembar')
                    ->badge()
                    ->size('xl')
                    ->weight('bold')
                    ->sortable(false)
                    ->color(fn($state) => $state >= 150 ? 'success' : ($state >= 40 ? 'warning' : 'danger')),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->tooltip(fn($state) => $state)
                    ->icon('heroicon-m-document-text')
                    ->placeholder('-')
                    ->wrap(),
            ])

            ->recordActions([
                // ACTION TAMBAH (Sudah disesuaikan untuk membagi rata inputan)
                Action::make('tambah')
                    ->label('Tambah')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->schema([
                        TextInput::make('tambah')
                            ->label('Tambah Total (Satu Meja)')
                            ->helperText('Angka ini akan dibagi rata ke semua pekerja di meja ini')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->prefix('+')
                            ->suffix(' lembar')
                            ->autofocus(),
                    ])
                    ->mountUsing(function ($form) {
                        $form->fill(['tambah' => 1]);
                    })
                    ->action(function ($record, array $data) use ($idProduksiRepair) {
                        $totalTambah = (int) $data['tambah'];
                        $rencanaIds = self::getGroupRencanaIds($record, $idProduksiRepair);

                        // Hitung jumlah pekerja untuk pembagian
                        $jumlahPekerja = count($rencanaIds);

                        if ($jumlahPekerja > 0) {
                            // Logika Pembagian (Math Logic)
                            $rataRata = floor($totalTambah / $jumlahPekerja);
                            $sisa = $totalTambah % $jumlahPekerja;

                            foreach ($rencanaIds as $index => $rencanaId) {
                                // Jika ada sisa pembagian, berikan ke orang-orang pertama dalam urutan
                                $tambahanPerOrang = $rataRata + ($index < $sisa ? 1 : 0);

                                if ($tambahanPerOrang <= 0) continue;

                                $rencana = RencanaRepair::find($rencanaId);
                                if (!$rencana) continue;

                                // Pastikan data produksi valid
                                $produksiExists = DB::table('produksi_repairs')->where('id', $rencana->id_produksi_repair)->exists();
                                if (!$produksiExists) continue;

                                $hasilExist = HasilRepair::where('id_rencana_repair', $rencanaId)->first();

                                if (!$hasilExist) {
                                    HasilRepair::create([
                                        'id_rencana_repair' => $rencanaId,
                                        'id_produksi_repair' => $rencana->id_produksi_repair,
                                        'jumlah' => $tambahanPerOrang,
                                    ]);
                                } else {
                                    $hasilExist->increment('jumlah', $tambahanPerOrang);
                                }
                            }
                        }
                    })
                    ->modalHeading(fn($record) => "Tambah Hasil - Meja {$record->nomor_meja}")
                    ->modalSubmitActionLabel('Tambah Sekarang'),

                // ACTION EDIT (Sudah disesuaikan agar yang diedit adalah Total Meja)
                Action::make('edit_hasil')
                    ->label('Edit Hasil')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->schema([
                        TextInput::make('total_meja')
                            ->label('Total Hasil Satu Meja')
                            ->helperText('Masukkan total gabungan semua pekerja di meja ini')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix(' lembar'),
                    ])
                    ->mountUsing(function ($form, $record) {
                        // Pre-fill form dengan TOTAL yang sudah ada (bukan rata-rata)
                        $form->fill([
                            'total_meja' => $record->total_hasil
                        ]);
                    })
                    ->action(function ($record, array $data) use ($idProduksiRepair) {
                        $totalMejaBaru = (int) $data['total_meja'];
                        $rencanaIds = self::getGroupRencanaIds($record, $idProduksiRepair);

                        $jumlahPekerja = count($rencanaIds);

                        if ($jumlahPekerja > 0) {
                            // Logika Pembagian Ulang
                            $rataRata = floor($totalMejaBaru / $jumlahPekerja);
                            $sisa = $totalMejaBaru % $jumlahPekerja;

                            foreach ($rencanaIds as $index => $rencanaId) {
                                $jatahPerOrang = $rataRata + ($index < $sisa ? 1 : 0);

                                $rencana = RencanaRepair::find($rencanaId);
                                if (!$rencana) continue;

                                $hasilExist = HasilRepair::where('id_rencana_repair', $rencanaId)->first();

                                if ($hasilExist) {
                                    $hasilExist->update(['jumlah' => $jatahPerOrang]);
                                } else {
                                    // Create jika data lama belum ada tapi user ingin isi nilai
                                    HasilRepair::create([
                                        'id_rencana_repair' => $rencanaId,
                                        'id_produksi_repair' => $rencana->id_produksi_repair,
                                        'jumlah' => $jatahPerOrang,
                                    ]);
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Hasil diperbarui!')
                                ->body("Total meja diset menjadi {$totalMejaBaru} lembar")
                                ->send();
                        }
                    })
                    ->modalHeading(fn($record) => "Edit Total Hasil - Meja {$record->nomor_meja}")
                    ->modalSubmitActionLabel('Simpan Perubahan'),

                // ACTION EDIT KETERANGAN (Tidak perlu ubah logic, hanya format)
                Action::make('edit_keterangan')
                    ->label('Keterangan')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('warning')
                    ->schema([
                        Textarea::make('keterangan')
                            ->label('Keterangan / Catatan')
                            ->rows(3)
                            ->maxLength(255),
                    ])
                    ->mountUsing(function ($form, $record) {
                        $form->fill(['keterangan' => $record->keterangan ?? '']);
                    })
                    ->action(function ($record, array $data) use ($idProduksiRepair) {
                        $keteranganBaru = $data['keterangan'];
                        $rencanaIds = self::getGroupRencanaIds($record, $idProduksiRepair);
                        $errorCount = 0;

                        foreach ($rencanaIds as $rencanaId) {
                            $rencana = RencanaRepair::find($rencanaId);
                            if (!$rencana) continue;

                            $hasilExist = HasilRepair::where('id_rencana_repair', $rencanaId)->first();

                            if ($hasilExist) {
                                $hasilExist->update(['keterangan' => $keteranganBaru]);
                            } else {
                                $produksiExists = DB::table('produksi_repairs')->where('id', $rencana->id_produksi_repair)->exists();
                                if (!$produksiExists) {
                                    $errorCount++;
                                    continue;
                                }

                                HasilRepair::create([
                                    'id_rencana_repair' => $rencanaId,
                                    'id_produksi_repair' => $rencana->id_produksi_repair,
                                    'jumlah' => 0,
                                    'keterangan' => $keteranganBaru,
                                ]);
                            }
                        }

                        if ($errorCount > 0) {
                            Notification::make()->warning()->title("{$errorCount} data dilewati")->send();
                        } else {
                            Notification::make()->success()->title('Keterangan disimpan')->send();
                        }
                    })
                    ->modalHeading(fn($record) => "Edit Keterangan - Meja {$record->nomor_meja}")
                    ->modalSubmitActionLabel('Simpan Catatan'),

                // ACTION DELETE (Hapus per Meja)
                Action::make('delete_hasil')
                    ->label('Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn($record) => "Hapus semua hasil untuk Meja {$record->nomor_meja}?")
                    ->action(function ($record) use ($idProduksiRepair) {
                        $rencanaIds = self::getGroupRencanaIds($record, $idProduksiRepair);
                        $deleted = HasilRepair::whereIn('id_rencana_repair', $rencanaIds)->delete();

                        if ($deleted > 0) {
                            Notification::make()->success()->title("Terhapus {$deleted} data")->send();
                        } else {
                            Notification::make()->warning()->title('Tidak ada data')->send();
                        }
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) use ($idProduksiRepair) {
                            foreach ($records as $record) {
                                $rencanaIds = self::getGroupRencanaIds($record, $idProduksiRepair);
                                HasilRepair::whereIn('id_rencana_repair', $rencanaIds)->delete();
                            }
                        }),
                ]),
            ])

            ->paginated([10, 25, 50])
            ->poll('6s')
            ->emptyStateHeading('Belum ada data hasil repair')
            ->emptyStateDescription('Silakan tambah hasil produksi')
            ->emptyStateIcon('heroicon-o-clipboard-document-list');
    }
}
