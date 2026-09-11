<?php

namespace App\Filament\Resources\ModalSandings\Schemas;

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
     * Eager load semua kemungkinan sumber hasil (HP, Graji, Sanding, Triplek Jadi,
     * Platform Mentah, Triplek Mentah) supaya accessor `hasil` & `barangSetengahJadi`
     * di SerahTerimaHp tidak N+1 dan tidak null untuk palet non-hotpress.
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
     * Kategori barang (PLYWOOD / PLATFORM) untuk ditampilkan di label opsi,
     * menggantikan label asal (Hotpress/Graji/dll).
     *
     * Barang dari Gudang Triplek Jadi & Gudang Triplek Mentah tidak menyimpan
     * kategori sendiri — isinya selalu Plywood, jadi di-hardcode. Barang dari
     * Gudang Platform Mentah selalu Platform.
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
     * Rapikan teks jadi Title Case: "S BETTER LOCAL" -> "Better Local",
     * "SENGON" -> "Sengon". Dipakai agar label opsi tidak berteriak huruf besar
     * dan lebih enak dibaca operator.
     */
    protected static function rapikan(?string $teks): string
    {
        $teks = trim((string) $teks);

        if ($teks === '') {
            return '-';
        }

        return ucwords(mb_strtolower($teks));
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Hidden::make('id_produksi_sanding')
                ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

            Hidden::make('id_barang_setengah_jadi'),

            /*
            |--------------------------------------------------------------------------
            | PILIH PALET (SERAH TERIMA) — SUMBER: HOTPRESS, GRAJI, TRIPLEK JADI,
            | PLATFORM MENTAH, ATAU TRIPLEK MENTAH
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
                        $hasilSanding = \App\Models\HasilSanding::with(['barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang'])->find(abs($state));
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

                    // Barang dari Gudang Triplek Jadi tidak punya barangSetengahJadi —
                    // label diambil langsung dari mutasi keluar (jenis kayu / grade / ukuran).
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

                    // Barang dari Gudang Platform Mentah — sama seperti Triplek Jadi,
                    // tidak punya barangSetengahJadi, label diambil dari mutasi keluar.
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

                    // Barang dari Gudang Triplek Mentah — sama pola dengan Platform
                    // Mentah / Triplek Jadi, tidak punya barangSetengahJadi, label
                    // diambil dari mutasi keluar.
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

                    // Accessor universal — jalan untuk semua sumber lama
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
                            $hasilSanding = \App\Models\HasilSanding::find(abs($idSerahTerima));
                            if (! $hasilSanding) {
                                return;
                            }
                            $sisa = $hasilSanding->kuantitas;
                            if ($value > $sisa) {
                                $fail("Jumlah melebihi sisa yang tersedia dari Hasil Sanding ({$sisa}).");
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
                            $fail("Jumlah melebihi sisa yang tersedia ({$sisa}).");
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
            ->with(self::HASIL_RELATIONS)
            ->get()
            ->map(function ($item) use ($currentId, $currentKuantitas) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentKuantitas : 0);
                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $tersedia = rtrim(rtrim(number_format($sisa, 2, '.', ''), '0'), '.');
                $kategori = self::kategoriLabel($item);

                if ($item->id_triplek_mutasi_keluar !== null) {
                    $m = $item->triplekMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'TJ'.$item->id_triplek_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (triplek jadi)";
                    return [$item->id => $label];
                }

                if ($item->id_platform_mth_mutasi_keluar !== null) {
                    $m = $item->platformMthMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'PM'.$item->id_platform_mth_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (platform mentah)";
                    return [$item->id => $label];
                }

                if ($item->id_triplek_mth_mutasi_keluar !== null) {
                    $m = $item->triplekMthMutasiKeluar;
                    $ukuranLabel = $m ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0) : '-';
                    $jenis = self::rapikan($m?->jenisKayu?->nama_kayu);
                    $gradeLabel = self::rapikan($m?->kw_grade);
                    $identitas = 'TM'.$item->id_triplek_mth_mutasi_keluar;
                    $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$jenis} {$gradeLabel} · {$tersedia} lbr (triplek mentah)";
                    return [$item->id => $label];
                }

                $hasil = $item->hasil;
                $barang = $item->barangSetengahJadi;
                $ukuran = $barang?->ukuran;
                $ukuranLabel = $ukuran ? ($ukuran->dimensi ?? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}") : '-';
                $namaJenisBarang = self::rapikan($barang?->jenisBarang?->nama_jenis_barang);
                $gradeLabel = self::rapikan($barang?->grade?->nama_grade);
                $noPalet = $hasil?->no_palet;
                $identitas = $noPalet !== null && $noPalet !== '' ? "P{$noPalet}" : 'P-';
                $asal = strtolower((string) $item->asal_label);

                $label = "{$identitas} · {$kategori} · {$ukuranLabel} {$namaJenisBarang} {$gradeLabel} · {$tersedia} lbr ({$asal})";
                return [$item->id => $label];
            })
            ->toArray();

        $usedHasilSandingIds = \App\Models\SerahTerimaHp::whereNotNull('id_hasil_sanding')->pluck('id_hasil_sanding');

        // Tambahkan Hasil Sanding yang BELUM DISERAH & BELUM DIBUATKAN SerahTerimaHp
        $hasilSandingOptions = \App\Models\HasilSanding::with(['barangSetengahJadi.ukuran', 'barangSetengahJadi.grade', 'barangSetengahJadi.jenisBarang', 'barangSetengahJadi.grade.kategoriBarang', 'produksiSanding'])
            ->whereNull('tujuan_serah')
            ->whereNull('diserahkan_at')
            ->whereNotIn('id', $usedHasilSandingIds)
            ->when($currentProduksiId, function ($query, $currentProduksiId) {
                // Pastikan tidak mengambil Hasil Sanding dari sesi produksi yang sama
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
                    $shiftChar = '';
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
                
                // Gunakan ID negatif agar bisa dibedakan dengan SerahTerimaHp
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
