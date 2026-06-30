<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DetailNotaBarangMasuksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_nota_bm')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nama_barang')
                    ->searchable(),
                TextColumn::make('jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('satuan')
                    ->searchable(),
                TextColumn::make('keterangan')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Action::make('tambah_veneer')
                    ->label('Tambah Veneer')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\Select::make('tipe_veneer')
                            ->label('Tipe Veneer')
                            ->options([
                                'basah' => 'Veneer Basah',
                                'kering' => 'Veneer Kering',
                            ])
                            ->required()
                            ->live(),

                        \Filament\Forms\Components\Select::make('id_ukuran')
                            ->label('Ukuran')
                            ->options(\App\Models\Ukuran::all()->pluck('nama_ukuran', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),

                        \Filament\Forms\Components\Select::make('id_jenis_kayu')
                            ->label('Jenis Kayu')
                            ->options(\App\Models\JenisKayu::pluck('nama_kayu', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),

                        \Filament\Forms\Components\Select::make('kw')
                            ->label('KW')
                            ->options([
                                '1' => 'KW 1',
                                '2' => 'KW 2',
                                '3' => 'KW 3',
                                '4' => 'KW 4',
                            ])
                            ->required()
                            ->live(),

                        \Filament\Forms\Components\Placeholder::make('stok_saat_ini')
                            ->label('Stok Saat Ini')
                            ->content(function (callable $get) {
                                $tipe = $get('tipe_veneer');
                                $idUkuran = $get('id_ukuran');
                                $idJenisKayu = $get('id_jenis_kayu');
                                $kw = $get('kw');

                                if (!$tipe || !$idUkuran || !$idJenisKayu || !$kw) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                }

                                if ($tipe === 'basah') {
                                    $ukuran = \App\Models\Ukuran::find($idUkuran);
                                    if (!$ukuran) {
                                        return new \Illuminate\Support\HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                    }

                                    $summary = \App\Models\HppVeneerBasahSummary::where([
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'panjang'       => $ukuran->panjang,
                                        'lebar'         => $ukuran->lebar,
                                        'tebal'         => $ukuran->tebal,
                                        'kw'            => $kw,
                                    ])->first();

                                    $stok = $summary ? (int) $summary->stok_lembar : 0;
                                } else {
                                    $latest = \App\Models\StokVeneerKering::where([
                                        'id_ukuran'     => $idUkuran,
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'kw'            => $kw,
                                    ])
                                    ->orderBy('tanggal_transaksi', 'desc')
                                    ->orderBy('id', 'desc')
                                    ->first();

                                    $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                }

                                if ($stok <= 0) {
                                    return new \Illuminate\Support\HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                }

                                return new \Illuminate\Support\HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($stok) . ' Lembar</strong>');
                            }),

                        \Filament\Forms\Components\TextInput::make('jumlah')
                            ->label('Jumlah (Lembar)')
                            ->numeric()
                            ->required(),

                        \Filament\Forms\Components\Textarea::make('keterangan')
                            ->label('Keterangan')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (RelationManager $livewire, array $data) {
                        $nota = $livewire->getOwnerRecord();
                        if (!$nota) {
                            return;
                        }

                        $mutasi = $nota->mutasi;
                        $isKeluar = $nota instanceof \App\Models\NotaBarangKeluar;

                        if (!$mutasi) {
                            $mutasi = \App\Models\VeneerMutasi::create([
                                'tanggal'        => $nota->tanggal,
                                'tipe_transaksi' => $isKeluar ? 'keluar' : 'masuk',
                                'no_nota'        => $nota->no_nota,
                                'tujuan_nota'    => $nota->tujuan_nota ?? '-',
                                'status'         => 'draft',
                                'id_nota_bk'     => $isKeluar ? $nota->id : null,
                                'id_nota_bm'     => $isKeluar ? null : $nota->id,
                                'dibuat_oleh'    => auth()->id(),
                            ]);
                        }

                        $ukuran = \App\Models\Ukuran::findOrFail($data['id_ukuran']);
                        $jenisKayu = \App\Models\JenisKayu::findOrFail($data['id_jenis_kayu']);

                        $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * (int)$data['jumlah']) / 10000000;

                        \App\Models\VeneerMutasiDetail::create([
                            'id_veneer_mutasi' => $mutasi->id,
                            'tipe_veneer'      => $data['tipe_veneer'],
                            'id_ukuran'        => $data['id_ukuran'],
                            'id_jenis_kayu'    => $data['id_jenis_kayu'],
                            'kw'               => $data['kw'],
                            'qty'              => (int) $data['jumlah'],
                            'm3'               => $m3,
                        ]);

                        $namaBarang = "Veneer " . ucfirst($data['tipe_veneer'])
                            . " - " . $ukuran->nama_ukuran
                            . " - " . $jenisKayu->nama_kayu
                            . " - KW " . $data['kw'];

                        if ($isKeluar) {
                            \App\Models\DetailNotaBarangKeluar::create([
                                'id_nota_bk'  => $nota->id,
                                'nama_barang' => $namaBarang,
                                'jumlah'      => (int) $data['jumlah'],
                                'satuan'      => 'Lembar',
                                'keterangan'  => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Keluar',
                            ]);
                        } else {
                            \App\Models\DetailNotaBarangMasuk::create([
                                'id_nota_bm'  => $nota->id,
                                'nama_barang' => $namaBarang,
                                'jumlah'      => (int) $data['jumlah'],
                                'satuan'      => 'Lembar',
                                'keterangan'  => $data['keterangan'] ?? 'Otomatis dari Mutasi Veneer Masuk',
                            ]);
                        }

                        $livewire->dispatch('$refresh');
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        // Hanya muncul jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    }),

                CreateAction::make()
                    ->label('Tambah Barang')
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        // Muncul jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    }),


                Action::make('validasi_nota')
                    ->label('Validasi Nota')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        if (!$nota)
                            return false;

                        // Tombol hanya muncul jika BELUM divalidasi
                        if (!empty($nota->divalidasi_oleh)) {
                            return false;
                        }

                        // Jika Super Admin, boleh lihat (bisa validasi)
                        $user = auth()->user();
                        if ($user && $user->hasAnyRole(['super_admin', 'Super Admin'])) {
                            return true;
                        }

                        // Pembuat TIDAK boleh validasi (hilangkan tombol)
                        return $nota->dibuat_oleh != auth()->id();
                    })
                    ->action(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();

                        try {
                            $hasMutasi = \App\Models\VeneerMutasi::where('id_nota_bm', $nota->id)->exists();
                            // Run the business service to add stock and set divalidasi_oleh
                            app(\App\Services\VeneerMutasiService::class)->processStockFromNota($nota);

                            Notification::make()
                                ->title('Nota berhasil divalidasi!')
                                ->body($hasMutasi ? 'Stok veneer telah ditambahkan sesuai isi nota BM.' : 'Status nota telah diperbarui.')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Validasi Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->after(fn($livewire) => $livewire->dispatch('$refresh')),


            ])

            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->form(function ($record) {
                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            return [
                                \Filament\Forms\Components\Select::make('tipe_veneer')
                                    ->label('Tipe Veneer')
                                    ->options([
                                        'basah' => 'Veneer Basah',
                                        'kering' => 'Veneer Kering',
                                    ])
                                    ->required()
                                    ->live(),

                                \Filament\Forms\Components\Select::make('id_ukuran')
                                    ->label('Ukuran')
                                    ->options(\App\Models\Ukuran::all()->pluck('nama_ukuran', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                \Filament\Forms\Components\Select::make('id_jenis_kayu')
                                    ->label('Jenis Kayu')
                                    ->options(\App\Models\JenisKayu::pluck('nama_kayu', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live(),

                                \Filament\Forms\Components\Select::make('kw')
                                    ->label('KW')
                                    ->options([
                                        '1' => 'KW 1',
                                        '2' => 'KW 2',
                                        '3' => 'KW 3',
                                        '4' => 'KW 4',
                                    ])
                                    ->required()
                                    ->live(),

                                \Filament\Forms\Components\Placeholder::make('stok_saat_ini')
                                    ->label('Stok Saat Ini')
                                    ->content(function (callable $get) {
                                        $tipe = $get('tipe_veneer');
                                        $idUkuran = $get('id_ukuran');
                                        $idJenisKayu = $get('id_jenis_kayu');
                                        $kw = $get('kw');

                                        if (!$tipe || !$idUkuran || !$idJenisKayu || !$kw) {
                                            return new \Illuminate\Support\HtmlString('<span class="text-gray-400 dark:text-gray-500">Silakan lengkapi pilihan di atas...</span>');
                                        }

                                        if ($tipe === 'basah') {
                                            $ukuran = \App\Models\Ukuran::find($idUkuran);
                                            if (!$ukuran) {
                                                return new \Illuminate\Support\HtmlString('<strong class="text-danger-600 dark:text-danger-400">0 Lembar</strong>');
                                            }

                                            $summary = \App\Models\HppVeneerBasahSummary::where([
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'panjang'       => $ukuran->panjang,
                                                'lebar'         => $ukuran->lebar,
                                                'tebal'         => $ukuran->tebal,
                                                'kw'            => $kw,
                                            ])->first();

                                            $stok = $summary ? (int) $summary->stok_lembar : 0;
                                        } else {
                                            $latest = \App\Models\StokVeneerKering::where([
                                                'id_ukuran'     => $idUkuran,
                                                'id_jenis_kayu' => $idJenisKayu,
                                                'kw'            => $kw,
                                            ])
                                            ->orderBy('tanggal_transaksi', 'desc')
                                            ->orderBy('id', 'desc')
                                            ->first();

                                            $stok = $latest ? (int) $latest->stok_lembar_sesudah : 0;
                                        }

                                        if ($stok <= 0) {
                                            return new \Illuminate\Support\HtmlString('<strong class="text-danger-600 dark:text-danger-400 text-lg">0 Lembar (Stok Habis)</strong>');
                                        }

                                        return new \Illuminate\Support\HtmlString('<strong class="text-success-600 dark:text-success-400 text-lg">' . number_format($stok) . ' Lembar</strong>');
                                    }),

                                \Filament\Forms\Components\TextInput::make('jumlah')
                                    ->label('Jumlah (Lembar)')
                                    ->numeric()
                                    ->required(),

                                \Filament\Forms\Components\Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->rows(3)
                                    ->required(),
                            ];
                        }

                        return [
                            \Filament\Forms\Components\TextInput::make('nama_barang')
                                ->label('Nama Barang')
                                ->required()
                                ->maxLength(255),

                            \Filament\Forms\Components\TextInput::make('jumlah')
                                ->label('Jumlah')
                                ->numeric()
                                ->required(),

                            \Filament\Forms\Components\TextInput::make('satuan')
                                ->label('Satuan')
                                ->required()
                                ->maxLength(50),

                            \Filament\Forms\Components\Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(3)
                                ->required(),
                        ];
                    })
                    ->mutateRecordDataUsing(function (array $data, $record): array {
                        $data['jumlah'] = (int) $record->jumlah;
                        $data['keterangan'] = $record->keterangan;

                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $nota = $record->nota;
                            if ($nota && $nota->mutasi) {
                                $mutasi = $nota->mutasi;
                                $matchingDetail = null;

                                $details = $mutasi->details()->with(['ukuran', 'jenisKayu'])->get();
                                foreach ($details as $detail) {
                                    $ukuran = $detail->ukuran;
                                    $jenisKayu = $detail->jenisKayu;
                                    if ($ukuran && $jenisKayu) {
                                        $expectedName = "Veneer " . ucfirst($detail->tipe_veneer)
                                            . " - " . $ukuran->nama_ukuran
                                            . " - " . $jenisKayu->nama_kayu
                                            . " - KW " . $detail->kw;

                                        if ($expectedName === $record->nama_barang && (int)$detail->qty === (int)$record->jumlah) {
                                            $matchingDetail = $detail;
                                            break;
                                        }
                                    }
                                }

                                if ($matchingDetail) {
                                    $data['tipe_veneer'] = $matchingDetail->tipe_veneer;
                                    $data['id_ukuran'] = $matchingDetail->id_ukuran;
                                    $data['id_jenis_kayu'] = $matchingDetail->id_jenis_kayu;
                                    $data['kw'] = $matchingDetail->kw;
                                }
                            }
                        }
                        return $data;
                    })
                    ->using(function ($record, array $data) {
                        if (str_starts_with($record->nama_barang, 'Veneer ')) {
                            $nota = $record->nota;
                            if ($nota && $nota->mutasi) {
                                $mutasi = $nota->mutasi;
                                $matchingDetail = null;

                                $details = $mutasi->details()->with(['ukuran', 'jenisKayu'])->get();
                                foreach ($details as $detail) {
                                    $ukuran = $detail->ukuran;
                                    $jenisKayu = $detail->jenisKayu;
                                    if ($ukuran && $jenisKayu) {
                                        $expectedName = "Veneer " . ucfirst($detail->tipe_veneer)
                                            . " - " . $ukuran->nama_ukuran
                                            . " - " . $jenisKayu->nama_kayu
                                            . " - KW " . $detail->kw;

                                        if ($expectedName === $record->nama_barang && (int)$detail->qty === (int)$record->jumlah) {
                                            $matchingDetail = $detail;
                                            break;
                                        }
                                    }
                                }

                                if ($matchingDetail) {
                                    $matchingDetail->update([
                                        'tipe_veneer'   => $data['tipe_veneer'],
                                        'id_ukuran'     => $data['id_ukuran'],
                                        'id_jenis_kayu' => $data['id_jenis_kayu'],
                                        'kw'            => $data['kw'],
                                        'qty'           => (int) $data['jumlah'],
                                    ]);

                                    // Recalculate m3
                                    $ukuranObj = \App\Models\Ukuran::findOrFail($data['id_ukuran']);
                                    $matchingDetail->m3 = ($ukuranObj->panjang * $ukuranObj->lebar * $ukuranObj->tebal * $matchingDetail->qty) / 10000000;
                                    $matchingDetail->save();
                                }
                            }

                            // Generate new nama_barang
                            $ukuran = \App\Models\Ukuran::findOrFail($data['id_ukuran']);
                            $jenisKayu = \App\Models\JenisKayu::findOrFail($data['id_jenis_kayu']);
                            $newNamaBarang = "Veneer " . ucfirst($data['tipe_veneer'])
                                . " - " . $ukuran->nama_ukuran
                                . " - " . $jenisKayu->nama_kayu
                                . " - KW " . $data['kw'];

                            $record->update([
                                'nama_barang' => $newNamaBarang,
                                'jumlah'      => (int) $data['jumlah'],
                                'keterangan'  => $data['keterangan'] ?? $record->keterangan,
                            ]);
                        } else {
                            $record->update([
                                'nama_barang' => $data['nama_barang'],
                                'jumlah'      => (int) $data['jumlah'],
                                'satuan'      => $data['satuan'],
                                'keterangan'  => $data['keterangan'] ?? null,
                            ]);
                        }

                        return $record;
                    })
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        return $nota && empty($nota->divalidasi_oleh);
                    }),
                DeleteAction::make()
                    ->visible(function (RelationManager $livewire) {
                        $nota = $livewire->getOwnerRecord();
                        // Hanya bisa delete jika belum divalidasi
                        return $nota && empty($nota->divalidasi_oleh);
                    })
                    ->before(function ($record) {
                        $nota = $record->nota;
                        if ($nota && $nota->mutasi && str_starts_with($record->nama_barang, 'Veneer ')) {
                            $mutasi = $nota->mutasi;
                            $matchingDetail = null;

                            $details = $mutasi->details()->with(['ukuran', 'jenisKayu'])->get();
                            foreach ($details as $detail) {
                                $ukuran = $detail->ukuran;
                                $jenisKayu = $detail->jenisKayu;
                                if ($ukuran && $jenisKayu) {
                                    $expectedName = "Veneer " . ucfirst($detail->tipe_veneer)
                                        . " - " . $ukuran->nama_ukuran
                                        . " - " . $jenisKayu->nama_kayu
                                        . " - KW " . $detail->kw;

                                    if ($expectedName === $record->nama_barang && (int)$detail->qty === (int)$record->jumlah) {
                                        $matchingDetail = $detail;
                                        break;
                                    }
                                }
                            }

                            if ($matchingDetail) {
                                $matchingDetail->delete();
                            }
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
