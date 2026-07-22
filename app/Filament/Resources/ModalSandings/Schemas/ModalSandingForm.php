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
     * Eager load semua kemungkinan sumber hasil (HP, Graji, Sanding, Triplek Jadi)
     * supaya accessor `hasil` & `barangSetengahJadi` di SerahTerimaHp
     * tidak N+1 dan tidak null untuk palet non-hotpress.
     */
    protected const HASIL_RELATIONS = [
        'platformHasilHp.barangSetengahJadi.ukuran',
        'platformHasilHp.barangSetengahJadi.grade',
        'platformHasilHp.barangSetengahJadi.jenisBarang',
        'hasilGrajiTriplek.barangSetengahJadiHp.ukuran',
        'hasilGrajiTriplek.barangSetengahJadiHp.grade',
        'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
        'hasilSanding.barangSetengahJadi.ukuran',
        'hasilSanding.barangSetengahJadi.grade',
        'hasilSanding.barangSetengahJadi.jenisBarang',
        'triplekMutasiKeluar.jenisKayu',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            Hidden::make('id_produksi_sanding')
                ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

            Hidden::make('id_barang_setengah_jadi'),

            /*
            |--------------------------------------------------------------------------
            | PILIH PALET (SERAH TERIMA) — SUMBER: HOTPRESS, GRAJI, ATAU TRIPLEK JADI
            |--------------------------------------------------------------------------
            */
            Select::make('id_serah_terima_hp')
                ->label('Pilih Palet (Serah Terima)')
                ->options(fn (?ModalSanding $record) => self::getPaletOptions($record))
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

    protected static function getPaletOptions(?ModalSanding $record): array
    {
        $currentId = $record?->id_serah_terima_hp;
        $currentKuantitas = (float) ($record?->kuantitas ?? 0);

        return SerahTerimaHp::query()
            ->where('diterima_oleh', '!=', '-')
            ->whereIn('tujuan', ['sanding'])
            // palet yang menuju sanding: dari HP (platform), dari Graji,
            // ATAU dari Gudang Triplek Jadi (id_triplek_mutasi_keluar).
            ->where(function ($q) {
                $q->whereNotNull('id_platform_hasil_hp')
                    ->orWhereNotNull('id_hasil_graji_triplek')
                    ->orWhereNotNull('id_triplek_mutasi_keluar');
            })
            ->with(self::HASIL_RELATIONS)
            ->get()
            ->map(function ($item) use ($currentId, $currentKuantitas) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentKuantitas : 0);

                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $sisaLabel = rtrim(rtrim(number_format($sisa, 2, '.', ''), '0'), '.');
                $asal = $item->asal_label;

                // Barang Triplek Jadi: rakit label dari mutasi keluar.
                if ($item->id_triplek_mutasi_keluar !== null) {
                    $m = $item->triplekMutasiKeluar;
                    $ukuranLabel = $m
                        ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                        : '-';
                    $jenis = strtoupper($m?->jenisKayu?->nama_kayu ?? '-');
                    $gradeLabel = $m?->kw_grade ?? '-';

                    $label = "({$asal}) - {$ukuranLabel} {$jenis} {$gradeLabel} — Sisa: {$sisaLabel}";

                    return [$item->id => $label];
                }

                // Sumber lama (hotpress / graji): pakai accessor universal.
                $hasil = $item->hasil;
                $barang = $item->barangSetengahJadi;
                $ukuran = $barang?->ukuran;

                $ukuranLabel = $ukuran ? ($ukuran->dimensi ?? "{$ukuran->panjang}x{$ukuran->lebar}x{$ukuran->tebal}") : '-';
                $kodeJenisBarang = strtoupper($barang?->jenisBarang?->kode_jenis_barang ?? '-');
                $gradeLabel = $barang?->grade?->nama_grade ?? '-';

                $label = "Palet {$hasil?->no_palet} ({$asal}) - {$ukuranLabel} {$kodeJenisBarang} {$gradeLabel} — Sisa: {$sisaLabel}";

                return [$item->id => $label];
            })
            ->toArray();
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