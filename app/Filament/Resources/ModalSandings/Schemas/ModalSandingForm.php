<?php

namespace App\Filament\Resources\ModalSandings\Schemas;

use App\Models\HasilSanding;
use App\Models\ModalSanding;
use App\Models\SerahTerimaHp;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ModalSandingForm
{
    /**
     * Eager load semua kemungkinan sumber hasil supaya accessor `hasil` &
     * `barangSetengahJadi` di SerahTerimaHp tidak N+1 dan tidak null.
     */
    protected const HASIL_RELATIONS = [
        'platformHasilHp.barangSetengahJadi.ukuran',
        'platformHasilHp.barangSetengahJadi.grade.kategoriBarang',
        'platformHasilHp.barangSetengahJadi.jenisBarang',
        'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
        'hasilGrajiTriplek.barangSetengahJadiHp.grade.kategoriBarang',
        'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
        'hasilSanding.barangSetengahJadi.ukuran',
        'hasilSanding.barangSetengahJadi.grade.kategoriBarang',
        'hasilSanding.barangSetengahJadi.jenisBarang',
        'triplekMutasiKeluar.jenisKayu',
        'platformMthMutasiKeluar.jenisKayu',
        'triplekMthMutasiKeluar.jenisKayu',
    ];

    /**
     * Kategori barang (PLYWOOD / PLATFORM) untuk label opsi.
     * Gudang Triplek Jadi & Triplek Mentah selalu Plywood, Platform Mentah selalu Platform.
     */
    protected static function kategoriLabel(SerahTerimaHp $item): string
    {
        if ($item->id_triplek_mutasi_keluar !== null) {
            return 'Plywood';
        }

        if ($item->id_triplek_mth_mutasi_keluar !== null) {
            return 'Plywood';
        }

        if ($item->id_platform_mth_mutasi_keluar !== null) {
            return 'Platform';
        }

        return $item->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori ?? '-';
    }

    /**
     * Rapikan teks jadi Title Case: "S BETTER LOCAL" -> "Better Local".
     */
    protected static function rapikan(?string $teks): string
    {
        $teks = trim((string) $teks);

        if ($teks === '') {
            return '-';
        }

        return ucwords(mb_strtolower($teks));
    }

    /**
     * Penanda untuk palet yang berasal dari produksi sanding lain (hari/shift lain).
     * Kosong jika palet milik produksi saat ini.
     */
    protected static function penandaHari(SerahTerimaHp $item, $currentProduksiId): string
    {
        if ($currentProduksiId && (int) $item->id_produksi_sanding === (int) $currentProduksiId) {
            return '';
        }

        $tgl = $item->created_at?->format('d/m');

        return $tgl ? " · sisa {$tgl}" : ' · sisa hari lain';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Hidden::make('id_produksi_sanding')
                ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

            Hidden::make('id_barang_setengah_jadi'),

            /*
            |--------------------------------------------------------------------------
            | PILIH PALET — SUMBER: GUDANG TRIPLEK MENTAH, GUDANG PLATFORM MENTAH,
            | BYPASS GRAJI TRIPLEK, ATAU HASIL SANDING YANG BELUM DISERAH
            |--------------------------------------------------------------------------
            */
            Select::make('id_serah_terima_hp')
                ->label('Pilih Palet (Serah Terima / Hasil Sanding)')
                ->options(fn (Get $get, ?ModalSanding $record) => self::getPaletOptions($get, $record))
                ->searchable()
                ->live()
                ->required()
                ->afterStateUpdated(function ($state, $set, ?ModalSanding $record) {
                    if (! $state) {
                        $set('id_barang_setengah_jadi', null);
                        $set('grade_label', null);
                        $set('jenis_barang_label', null);
                        $set('ukuran_label', null);
                        $set('sisa_tersedia', null);
                        $set('kuantitas', null);

                        return;
                    }

                    if ($state < 0) {
                        $hasilSanding = HasilSanding::with(['barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang'])->find(abs($state));
                        $barang = $hasilSanding?->barangSetengahJadi;

                        $set('id_barang_setengah_jadi', $barang?->id);
                        $set('grade_label', $barang?->grade?->nama_grade ?? '-');
                        $set('jenis_barang_label', $barang?->jenisBarang?->nama_jenis_barang ?? '-');
                        $set('ukuran_label', $barang?->ukuran?->dimensi ?? '-');
                        $set('sisa_tersedia', $hasilSanding?->kuantitas ?? 0);
                        $set('kuantitas', $hasilSanding?->kuantitas ?? 0);

                        return;
                    }

                    $serahTerima = SerahTerimaHp::with(self::HASIL_RELATIONS)->find($state);

                    $sisa = $serahTerima?->sisa ?? 0;
                    if ($record && $record->id_serah_terima_hp === (int) $state) {
                        $sisa += (float) $record->kuantitas;
                    }

                    // Gudang Triplek Jadi (hanya muncul untuk record lama yang sedang diedit)
                    if ($serahTerima?->id_triplek_mutasi_keluar !== null) {
                        $m = $serahTerima?->triplekMutasiKeluar;

                        $set('id_barang_setengah_jadi', null);
                        $set('grade_label', $m?->kw_grade ?? '-');
                        $set('jenis_barang_label', $m?->jenisKayu?->nama_kayu ?? '-');
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : '-');
                        $set('sisa_tersedia', $sisa);
                        $set('kuantitas', $sisa);

                        return;
                    }

                    // Gudang Platform Mentah
                    if ($serahTerima?->id_platform_mth_mutasi_keluar !== null) {
                        $m = $serahTerima?->platformMthMutasiKeluar;

                        $set('id_barang_setengah_jadi', null);
                        $set('grade_label', $m?->kw_grade ?? '-');
                        $set('jenis_barang_label', $m?->jenisKayu?->nama_kayu ?? '-');
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : '-');
                        $set('sisa_tersedia', $sisa);
                        $set('kuantitas', $sisa);

                        return;
                    }

                    // Gudang Triplek Mentah
                    if ($serahTerima?->id_triplek_mth_mutasi_keluar !== null) {
                        $m = $serahTerima?->triplekMthMutasiKeluar;

                        $set('id_barang_setengah_jadi', null);
                        $set('grade_label', $m?->kw_grade ?? '-');
                        $set('jenis_barang_label', $m?->jenisKayu?->nama_kayu ?? '-');
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : '-');
                        $set('sisa_tersedia', $sisa);
                        $set('kuantitas', $sisa);

                        return;
                    }

                    // Accessor universal (Bypass Graji Triplek, dll)
                    $barang = $serahTerima?->barangSetengahJadi;

                    $set('id_barang_setengah_jadi', $barang?->id);
                    $set('grade_label', $barang?->grade?->nama_grade ?? '-');
                    $set('jenis_barang_label', $barang?->jenisBarang?->nama_jenis_barang ?? '-');
                    $set('ukuran_label', $barang?->ukuran?->dimensi ?? '-');
                    $set('sisa_tersedia', $sisa);
                    $set('kuantitas', $sisa);
                }),

            TextInput::make('sisa_tersedia')
                ->label('Sisa Tersedia')
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(function ($set, ?ModalSanding $record) {
                    if (! $record?->serahTerimaHp) {
                        return;
                    }

                    $set('sisa_tersedia', $record->serahTerimaHp->sisa + (float) $record->kuantitas);
                }),

            TextInput::make('grade_label')
                ->label('Grade')
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(function ($set, ?ModalSanding $record) {
                    $serah = $record?->serahTerimaHp;

                    if (! $serah) {
                        return;
                    }

                    if ($serah->id_triplek_mutasi_keluar !== null) {
                        $set('grade_label', $serah->triplekMutasiKeluar?->kw_grade);

                        return;
                    }

                    if ($serah->id_platform_mth_mutasi_keluar !== null) {
                        $set('grade_label', $serah->platformMthMutasiKeluar?->kw_grade);

                        return;
                    }

                    if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                        $set('grade_label', $serah->triplekMthMutasiKeluar?->kw_grade);

                        return;
                    }

                    $set('grade_label', $serah->barangSetengahJadi?->grade?->nama_grade);
                }),

            TextInput::make('jenis_barang_label')
                ->label('Jenis Barang')
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(function ($set, ?ModalSanding $record) {
                    $serah = $record?->serahTerimaHp;

                    if (! $serah) {
                        return;
                    }

                    if ($serah->id_triplek_mutasi_keluar !== null) {
                        $set('jenis_barang_label', $serah->triplekMutasiKeluar?->jenisKayu?->nama_kayu);

                        return;
                    }

                    if ($serah->id_platform_mth_mutasi_keluar !== null) {
                        $set('jenis_barang_label', $serah->platformMthMutasiKeluar?->jenisKayu?->nama_kayu);

                        return;
                    }

                    if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                        $set('jenis_barang_label', $serah->triplekMthMutasiKeluar?->jenisKayu?->nama_kayu);

                        return;
                    }

                    $set('jenis_barang_label', $serah->barangSetengahJadi?->jenisBarang?->nama_jenis_barang);
                }),

            TextInput::make('ukuran_label')
                ->label('Ukuran')
                ->disabled()
                ->dehydrated(false)
                ->afterStateHydrated(function ($set, ?ModalSanding $record) {
                    $serah = $record?->serahTerimaHp;

                    if (! $serah) {
                        return;
                    }

                    if ($serah->id_triplek_mutasi_keluar !== null) {
                        $m = $serah->triplekMutasiKeluar;
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : null);

                        return;
                    }

                    if ($serah->id_platform_mth_mutasi_keluar !== null) {
                        $m = $serah->platformMthMutasiKeluar;
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : null);

                        return;
                    }

                    if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                        $m = $serah->triplekMthMutasiKeluar;
                        $set('ukuran_label', $m
                            ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                            : null);

                        return;
                    }

                    $set('ukuran_label', $serah->barangSetengahJadi?->ukuran?->dimensi);
                }),

            /*
            |--------------------------------------------------------------------------
            | KUANTITAS — satu-satunya angka jumlah yang bisa diedit bebas
            |--------------------------------------------------------------------------
            */
            TextInput::make('kuantitas')
                ->label('Kuantitas Dipakai')
                ->numeric()
                ->minValue(1)
                ->required()
                ->rules([
                    fn (Get $get, ?ModalSanding $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                        $idSerahTerima = $get('id_serah_terima_hp');

                        if (! $idSerahTerima) {
                            return;
                        }

                        if ($idSerahTerima < 0) {
                            $hasilSanding = HasilSanding::find(abs($idSerahTerima));
                            if (! $hasilSanding) {
                                return;
                            }
                            $sisa = $hasilSanding->kuantitas;
                            if ($value > $sisa) {
                                // $fail("Jumlah melebihi sisa yang tersedia dari Hasil Sanding ({$sisa})."); // Dibuka sementara agar bisa over qty
                            }

                            return;
                        }

                        $serahTerima = SerahTerimaHp::find($idSerahTerima);

                        if (! $serahTerima) {
                            return;
                        }

                        $sisa = $serahTerima->sisa;

                        if ($record && $record->id_serah_terima_hp === (int) $idSerahTerima) {
                            $sisa += (float) $record->kuantitas;
                        }

                        if ($value > $sisa) {
                            // $fail("Jumlah melebihi sisa yang tersedia ({$sisa})."); // Dibuka sementara agar bisa over qty
                        }
                    },
                ]),

            /*
            |--------------------------------------------------------------------------
            | JUMLAH PASS SANDING — tetap bisa diedit
            |--------------------------------------------------------------------------
            */
            TextInput::make('jumlah_sanding_face')
                ->label('Jumlah Sanding Face (Pass)')
                ->numeric()
                ->minValue(1)
                ->required(),

            TextInput::make('jumlah_sanding_back')
                ->label('Jumlah Sanding Back (Pass)')
                ->numeric()
                ->minValue(1)
                ->required(),

            /*
            |--------------------------------------------------------------------------
            | NO PALET — auto-generate, disabled, tapi tetap tersimpan
            |--------------------------------------------------------------------------
            */
            TextInput::make('no_palet')
                ->label('No Palet')
                ->disabled()
                ->dehydrated(true)
                ->default(function (callable $get) {
                    $idProduksi = $get('id_produksi_sanding');
                    if (! $idProduksi) {
                        return null;
                    }

                    return self::generateNextNoPalet($idProduksi);
                }),
        ]);
    }

    protected static function getPaletOptions(Get $get, ?ModalSanding $record): array
    {
        $currentId = $record?->id_serah_terima_hp;
        $currentKuantitas = (float) ($record?->kuantitas ?? 0);
        $currentProduksiId = $get('id_produksi_sanding') ?? $record?->id_produksi_sanding;

        $serahTerimaOptions = SerahTerimaHp::query()
            ->where('diterima_oleh', '!=', '-')
            ->where('tujuan', 'sanding')
            ->whereNull('ditolak_oleh')
            // Hanya 3 sumber SerahTerimaHp (sumber ke-4 = Hasil Sanding belum diserah, di bawah):
            // 1. Gudang Triplek Mentah
            // 2. Gudang Platform Mentah
            // 3. Bypass Graji Triplek
            // Record yang sedang diedit tetap ikut agar form edit tidak kosong.
            ->where(function ($query) use ($currentId) {
                $query->whereNotNull('id_triplek_mth_mutasi_keluar')
                    ->orWhereNotNull('id_platform_mth_mutasi_keluar')
                    // Bypass Graji Triplek: hasil graji yang langsung diserahkan ke sanding
                    // (sudah dibatasi tujuan = 'sanding' di atas)
                    ->orWhereNotNull('id_hasil_graji_triplek');

                if ($currentId) {
                    $query->orWhere('id', $currentId);
                }
            })
            // Batas 1 minggu untuk semua palet (termasuk milik produksi ini).
            ->where(function ($query) use ($currentId) {
                $query->whereDate('created_at', '>=', now()->subDays(7)->toDateString());

                if ($currentId) {
                    $query->orWhere('id', $currentId);
                }
            })
            ->with(self::HASIL_RELATIONS)
            ->get()
            ->map(function ($item) use ($currentId, $currentKuantitas) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentKuantitas : 0);

                return [$item, $sisa];
            })
            ->filter(fn ($pair) => round($pair[1], 4) > 0)
            // Palet produksi ini tampil paling atas, palet hari lain di bawah
            ->sortBy(fn ($pair) => $currentProduksiId
                && (int) $pair[0]->id_produksi_sanding === (int) $currentProduksiId
                    ? 0
                    : 1
            )
            ->mapWithKeys(function ($pair) use ($currentProduksiId) {
                [$item, $sisa] = $pair;
                $tersedia = rtrim(rtrim(number_format($sisa, 2, '.', ''), '0'), '.');
                $kategori = self::kategoriLabel($item);
                $hari = self::penandaHari($item, $currentProduksiId);

                // Gudang Triplek Jadi (hanya record lama yang sedang diedit)
                if ($item->id_triplek_mutasi_keluar !== null) {
                    $m = $item->triplekMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'TJ'.$item->id_triplek_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (triplek jadi){$hari}";

                    return [$item->id => $label];
                }

                // Gudang Platform Mentah
                if ($item->id_platform_mth_mutasi_keluar !== null) {
                    $m = $item->platformMthMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'PM'.$item->id_platform_mth_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (platform mentah){$hari}";

                    return [$item->id => $label];
                }

                // Gudang Triplek Mentah
                if ($item->id_triplek_mth_mutasi_keluar !== null) {
                    $m = $item->triplekMthMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'TM'.$item->id_triplek_mth_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (triplek mentah){$hari}";

                    return [$item->id => $label];
                }

                // Bypass Graji Triplek (sumber WIP)
                $hasil = $item->hasil;
                $barang = $item->barangSetengahJadi;
                $ukuran = $barang?->ukuran;
                $ukuranLabel = $ukuran ? ($ukuran->dimensi ?? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}") : '-';
                $namaJenisBarang = self::rapikan($barang?->jenisBarang?->nama_jenis_barang);
                $gradeLabel = self::rapikan($barang?->grade?->nama_grade);
                $noPalet = $hasil?->no_palet;
                $identitas = $noPalet !== null && $noPalet !== '' ? "P{$noPalet}" : 'P-';
                $asal = strtolower((string) $item->asal_label);

                $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$namaJenisBarang} {$gradeLabel} · {$tersedia} lbr ({$asal}){$hari}";

                return [$item->id => $label];
            })
            ->toArray();

        $usedHasilSandingIds = SerahTerimaHp::whereNotNull('id_hasil_sanding')->pluck('id_hasil_sanding');

        // Hasil Sanding yang BELUM DISERAH & BELUM DIBUATKAN SerahTerimaHp
        $hasilSandingOptions = HasilSanding::with(['barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang', 'barangSetengahJadi.grade.kategoriBarang', 'produksiSanding'])
            ->whereNull('tujuan_serah')
            ->whereNull('diserahkan_at')
            ->whereNotIn('id', $usedHasilSandingIds)
            // Hanya Hasil Sanding dari produksi 7 hari terakhir
            ->whereHas('produksiSanding', function ($query) {
                $query->whereDate('tanggal', '>=', now()->subDays(7)->toDateString());
            })
            ->when($currentProduksiId, function ($query, $currentProduksiId) {
                return $query->where('id_produksi_sanding', '!=', $currentProduksiId);
            })
            ->get()
            ->mapWithKeys(function ($hs) {
                $barang = $hs->barangSetengahJadi;
                $kategori = $barang?->grade?->kategoriBarang?->nama_kategori ?? '-';
                $ukuranLabel = $barang?->ukuran?->dimensi ?? '-';
                $namaJenisBarang = self::rapikan($barang?->jenisBarang?->nama_jenis_barang);
                $gradeLabel = self::rapikan($barang?->grade?->nama_grade);

                $produksi = $hs->produksiSanding;
                $identitas = 'HS-';
                if ($produksi && $produksi->tanggal) {
                    $tgl = $produksi->tanggal->format('d/m');
                    $shift = strtolower(trim($produksi->shift ?? ''));
                    if ($shift === 'pagi') {
                        $shiftChar = 'p';
                    } elseif ($shift === 'malam') {
                        $shiftChar = 'm';
                    } else {
                        $shiftChar = substr($shift, 0, 1);
                    }
                    $identitas = "HS-{$tgl}/{$shiftChar}";
                } else {
                    $noPalet = $hs->no_palet;
                    $identitas = $noPalet !== null && $noPalet !== '' ? "HS{$noPalet}" : 'HS-';
                }

                $tersedia = rtrim(rtrim(number_format($hs->kuantitas, 2, '.', ''), '0'), '.');
                $status = $hs->status ?? 'Belum Selesai';

                $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$namaJenisBarang} {$gradeLabel} · {$tersedia} lbr ({$status})";

                // ID negatif agar bisa dibedakan dengan SerahTerimaHp
                return [-$hs->id => $label];
            })
            ->toArray();

        return $serahTerimaOptions + $hasilSandingOptions;
    }

    /**
     * Nomor palet hasil sanding berikutnya untuk produksi ini (auto-increment per produksi).
     */
    protected static function generateNextNoPalet(int $idProduksi): int
    {
        $lastNoPalet = ModalSanding::where('id_produksi_sanding', $idProduksi)
            ->max('no_palet');

        return ((int) $lastNoPalet) + 1;
    }
}
