<?php

namespace App\Filament\Resources\BahanHotPresses\Schemas;

use App\Models\BahanHotpress;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\JenisBarang;
use App\Models\Grade;
use App\Models\PlatformJadiMutasiKeluar;
use App\Models\PlatformJadiMutasiKeluarPalet;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;

class BahanHotPressForm
{
    public static function configure(Schema $schema): Schema
    {
        $getLastRencana = fn($livewire) =>
        $livewire->ownerRecord
            ?->rencanaKerjaHp()
            ->latest()
            ->with('barangSetengahJadiHp')
            ->first();

        return $schema
            ->columns(2)
            ->components([
                Select::make('grade_id')
                    ->label('Filter Grade')
                    ->options(
                        Grade::with('kategoriBarang')
                            ->orderBy('id_kategori_barang')
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' . $g->nama_grade
                            ])
                    )
                    ->live()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                // =========================================================================
                // 2. FILTER JENIS BARANG (OPSIONAL) - TIDAK DEHYDRATED
                // =========================================================================
                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->live()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                // =========================================================================
                // 3. BARANG SETENGAH JADI (SELECT UTAMA) - DEHYDRATED (Disimpan)
                // =========================================================================
                Select::make('barang_setengah_jadi_selector') // ganti nama, bukan lagi id_mutasi_keluar_palet
                    ->label('Barang Setengah Jadi')
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->dehydrated(false)   // <-- tidak disimpan langsung, cuma UI selector
                    ->options(function (?BahanHotpress $record, $livewire, Get $get) {
                        $ownerId    = $livewire->ownerRecord?->id;
                        $gradeId    = $get('grade_id');
                        $jenisId    = $get('jenis_barang_id_filter');

                        $namaGrade = $gradeId ? Grade::find($gradeId)?->nama_grade : null;

                        return self::getPaletOptions($record, $ownerId, $namaGrade, $jenisId)
                            + self::getPlatformOptions($record, $ownerId, $namaGrade, $jenisId);
                    })
                    ->afterStateHydrated(function ($set, ?BahanHotpress $record) {
                        // saat edit, prefill selector dari kolom asli record
                        if ($record?->sumber === 'veneer' && $record->id_mutasi_keluar_palet) {
                            $set('barang_setengah_jadi_selector', "veneer:{$record->id_mutasi_keluar_palet}");
                        } elseif ($record?->sumber === 'platform' && $record->id_mutasi_keluar_platform) {
                            $set('barang_setengah_jadi_selector', "platform:{$record->id_mutasi_keluar_platform}");
                        }
                    })
                    ->afterStateUpdated(function ($state, callable $set, ?BahanHotpress $record) {
                        if (! $state || ! str_contains($state, ':')) {
                            $set('isi', null);
                            $set('no_palet', null);
                            $set('sisa_tersedia', null);
                            $set('sumber', null);
                            $set('id_mutasi_keluar_palet', null);
                            $set('id_mutasi_keluar_platform', null);
                            return;
                        }

                        [$sumber, $id] = explode(':', $state, 2);

                        if ($sumber === 'veneer') {
                            $palet = VeneerJadiMutasiKeluarPalet::find($id);
                            if (! $palet) return;

                            $sisa = $palet->sisa;
                            if ($record && $record->sumber === 'veneer' && $record->id_mutasi_keluar_palet === (int) $id) {
                                $sisa += (float) $record->isi;
                            }

                            $set('no_palet', $palet->nomor_palet);
                            $set('sisa_tersedia', $sisa);
                            $set('isi', $sisa);
                            $set('sumber', 'veneer');
                            $set('id_mutasi_keluar_palet', (int) $id);
                            $set('id_mutasi_keluar_platform', null);
                        } else {
                            $palet = PlatformJadiMutasiKeluarPalet::find($id);
                            if (! $palet) return;

                            $sisa = $palet->sisa;
                            if ($record && $record->sumber === 'platform' && $record->id_mutasi_keluar_platform === (int) $id) {
                                $sisa += (float) $record->isi;
                            }

                            $set('no_palet', $palet->nomor_palet);
                            $set('sisa_tersedia', $sisa);
                            $set('isi', $sisa);
                            $set('sumber', 'platform');
                            $set('id_mutasi_keluar_platform', (int) $id);
                            $set('id_mutasi_keluar_palet', null);
                        }
                    })
                    ->columnSpanFull(),

                Hidden::make('sumber'),
                Hidden::make('id_mutasi_keluar_palet'),
                Hidden::make('id_mutasi_keluar_platform'),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia (Lembar)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?BahanHotpress $record) {
                        if (! $record?->sumber) {
                            return;
                        }

                        if ($record->sumber === 'veneer' && $record->id_mutasi_keluar_palet) {
                            $palet = VeneerJadiMutasiKeluarPalet::find($record->id_mutasi_keluar_palet);
                            if ($palet) {
                                $set('sisa_tersedia', $palet->sisa + (float) $record->isi);
                            }
                        } elseif ($record->sumber === 'platform' && $record->id_mutasi_keluar_platform) {
                            $palet = PlatformJadiMutasiKeluarPalet::find($record->id_mutasi_keluar_platform);
                            if ($palet) {
                                $set('sisa_tersedia', $palet->sisa + (float) $record->isi);
                            }
                        }
                    }),

                TextInput::make('isi')
                    ->label('Isi (Digunakan)')
                    ->numeric()
                    ->required()
                    ->live()
                    ->rules([
                        fn(Get $get, ?BahanHotpress $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $sumber = $get('sumber');
                            $rawState = $get('barang_setengah_jadi_selector'); // <-- bukan lagi 'id_mutasi_keluar_palet'

                            if (! $rawState || ! str_contains($rawState, ':')) {
                                return;
                            }

                            [$src, $id] = explode(':', $rawState, 2);

                            if ($src === 'veneer') {
                                $palet = VeneerJadiMutasiKeluarPalet::find($id);
                                if (! $palet) return;
                                $sisa = $palet->sisa;
                                if ($record && $record->sumber === 'veneer' && $record->id_mutasi_keluar_palet === (int) $id) {
                                    $sisa += (float) $record->isi;
                                }
                            } else {
                                $palet = PlatformJadiMutasiKeluarPalet::find($id);
                                if (! $palet) return;
                                $sisa = $palet->sisa;
                                if ($record && $record->sumber === 'platform' && $record->id_mutasi_keluar_platform === (int) $id) {
                                    $sisa += (float) $record->isi;
                                }
                            }

                            if ($value > $sisa) {
                                $fail("Isi melebihi sisa yang tersedia ({$sisa} lembar).");
                            }
                        },
                    ]),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required()
                    ->live(),
            ]);
    }

    protected static function getPaletOptions(?BahanHotpress $record, $ownerRecordId = null, ?string $namaGrade = null, $jenisId = null): array
    {
        $currentId = $record?->sumber === 'veneer' ? $record?->id_mutasi_keluar_palet : null;
        $currentIsi = (float) ($record?->isi ?? 0);

        return VeneerJadiMutasiKeluarPalet::query()
            ->whereNotNull('diterima_by')
            ->whereHas('mutasiKeluar', function ($q) use ($ownerRecordId, $namaGrade, $jenisId) {
                if ($ownerRecordId) {
                    $q->where('id_produksi_hp', $ownerRecordId);
                }
                if ($namaGrade) {
                    $q->where('kw_grade', $namaGrade);
                }
                if ($jenisId) {
                    $q->where('id_jenis_kayu', $jenisId);
                }
            })
            ->with('mutasiKeluar.jenisKayu')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($palet) use ($currentId, $currentIsi) {
                $sisa = $palet->sisa + ($palet->id === $currentId ? $currentIsi : 0);
                return [$palet, $sisa];
            })
            ->filter(fn($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$palet, $sisa] = $pair;
                $mk = $palet->mutasiKeluar;

                $panjang = (float) $mk->panjang + 0;
                $lebar   = (float) $mk->lebar + 0;
                $tebal   = (float) $mk->tebal + 0;
                $kw      = $mk->kw_grade ?? '?';
                $kayu    = $mk->jenisKayu?->nama_kayu ?? '?';
                $noPalet = $palet->nomor_palet ?? '?';

                $label = "Veneer | {$panjang}mm x {$lebar}mm x {$tebal}mm | {$kw} | {$kayu} "
                    . "| Palet {$noPalet} | Sisa {$sisa} Lbr";

                return ["veneer:{$palet->id}" => $label];
            })
            ->toArray();
    }

    protected static function getPlatformOptions(?BahanHotpress $record, $ownerRecordId = null, ?string $namaGrade = null, $jenisId = null): array
    {
        $currentId = $record?->sumber === 'platform' ? $record?->id_mutasi_keluar_platform : null;
        $currentIsi = (float) ($record?->isi ?? 0);

        return PlatformJadiMutasiKeluarPalet::query()
            ->whereNotNull('diterima_by')
            ->whereHas('mutasiKeluar', function ($q) use ($ownerRecordId, $namaGrade, $jenisId) {
                if ($ownerRecordId) {
                    $q->where('id_produksi_hp', $ownerRecordId);
                }
                if ($namaGrade) {
                    $q->where('kw_grade', $namaGrade);
                }
                if ($jenisId) {
                    $q->where('id_jenis_barang', $jenisId);
                }
            })
            ->with('mutasiKeluar.jenisBarang')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($palet) use ($currentId, $currentIsi) {
                $sisa = $palet->sisa + ($palet->id === $currentId ? $currentIsi : 0);
                return [$palet, $sisa];
            })
            ->filter(fn($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$palet, $sisa] = $pair;
                $mk = $palet->mutasiKeluar;

                $panjang = (float) $mk->panjang + 0;
                $lebar   = (float) $mk->lebar + 0;
                $tebal   = (float) $mk->tebal + 0;
                $kw      = $mk->kw_grade ?? '?';
                $jenisBarang = $mk->jenisBarang?->nama_jenis_barang ?? '?';
                $noPalet = $palet->nomor_palet ?? '?';

                $label = "Platform | {$panjang}mm x {$lebar}mm x {$tebal}mm | {$kw} | {$jenisBarang} "
                    . "| Palet {$noPalet} | Sisa {$sisa} Lbr";

                return ["platform:{$palet->id}" => $label];
            })
            ->toArray();
    }
}
