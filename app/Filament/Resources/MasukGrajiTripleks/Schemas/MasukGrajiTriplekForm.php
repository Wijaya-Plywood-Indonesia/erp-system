<?php

namespace App\Filament\Resources\MasukGrajiTripleks\Schemas;

use App\Models\MasukGrajiTriplek;
use App\Models\SerahTerimaHp;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MasukGrajiTriplekForm
{
    /**
     * Eager load sumber barang menuju Graji Triplek yang DIDUKUNG di form ini:
     * hasil Hotpress (triplekHasilHp) ATAU mutasi keluar dari Gudang Triplek
     * Mentah. Sengaja TIDAK termasuk `hasilSanding` (serah manual Sanding ->
     * Graji) — kalau nanti sumber itu mau diaktifkan di sini, tambahkan
     * relasinya ke sini DAN ke whereSumberValid() di bawah secara eksplisit.
     */
    protected const HASIL_RELATIONS = [
        'triplekHasilHp.barangSetengahJadi.ukuran',
        'triplekHasilHp.barangSetengahJadi.grade',
        'triplekHasilHp.barangSetengahJadi.jenisBarang',
        'triplekMthMutasiKeluar.jenisKayu',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('id_produksi_graji_triplek')
                    ->default(fn ($livewire) => $livewire->getOwnerRecord()?->id),

                Hidden::make('id_barang_setengah_jadi_hp'),

                Select::make('id_serah_terima_hp')
                    ->label('Pilih Palet (Serah Terima)')
                    ->options(fn (?MasukGrajiTriplek $record) => self::getPaletOptions($record))
                    ->searchable()
                    ->live()
                    ->required()
                    ->afterStateUpdated(function ($state, $set, ?MasukGrajiTriplek $record) {
                        if (! $state) {
                            $set('id_barang_setengah_jadi_hp', null);
                            $set('no_palet', null);
                            $set('jenis_barang_label', null);
                            $set('ukuran_label', null);
                            $set('sisa_tersedia', null);
                            $set('isi', null);

                            return;
                        }

                        $serahTerima = SerahTerimaHp::with(self::HASIL_RELATIONS)->find($state);

                        $sisa = $serahTerima?->sisa ?? 0;
                        if ($record && $record->id_serah_terima_hp === (int) $state) {
                            $sisa += (float) $record->isi;
                        }

                        // Barang dari Gudang Triplek Mentah tidak punya barangSetengahJadi
                        // ataupun no_palet asli — label diambil dari mutasi keluar, dan
                        // no_palet di-generate ("TM-{id mutasi}") karena kolomnya NOT NULL.
                        if ($serahTerima?->id_triplek_mth_mutasi_keluar !== null) {
                            $m = $serahTerima?->triplekMthMutasiKeluar;

                            $set('id_barang_setengah_jadi_hp', null);
                            $set('no_palet', 'TM-'.$serahTerima->id_triplek_mth_mutasi_keluar);
                            $set('jenis_barang_label', $m?->jenisKayu?->nama_kayu ?? '-');
                            $set('ukuran_label', $m
                                ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                                : '-');
                            $set('sisa_tersedia', $sisa);
                            $set('isi', $sisa);

                            return;
                        }

                        // Sumber Hotpress (triplekHasilHp) — punya barangSetengahJadi & no_palet.
                        $hasil = $serahTerima?->triplekHasilHp;
                        $barang = $hasil?->barangSetengahJadi;

                        $set('id_barang_setengah_jadi_hp', $barang?->id);
                        $set('no_palet', $hasil?->no_palet);
                        $set('jenis_barang_label', $barang?->jenisBarang?->nama_jenis_barang ?? '-');
                        $set('ukuran_label', $barang?->ukuran?->nama_ukuran ?? '-');
                        $set('sisa_tersedia', $sisa);
                        $set('isi', $sisa);
                    }),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?MasukGrajiTriplek $record) {
                        if (! $record?->serahTerimaHp) {
                            return;
                        }

                        $set('sisa_tersedia', $record->serahTerimaHp->sisa + (float) $record->isi);
                    }),

                TextInput::make('isi')
                    ->label('Jumlah Masuk')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn (Get $get, ?MasukGrajiTriplek $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
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
                                $sisa += (float) $record->isi;
                            }

                            if ($value > $sisa) {
                                $fail("Jumlah melebihi sisa yang tersedia ({$sisa}).");
                            }
                        },
                    ]),

                TextInput::make('jenis_barang_label')
                    ->label('Jenis Barang')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?MasukGrajiTriplek $record) {
                        $serah = $record?->serahTerimaHp;

                        if (! $serah) {
                            return;
                        }

                        if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                            $set('jenis_barang_label', $serah->triplekMthMutasiKeluar?->jenisKayu?->nama_kayu);

                            return;
                        }

                        if (! $record?->barangSetengahJadiHp) {
                            return;
                        }

                        $set('jenis_barang_label', $record->barangSetengahJadiHp->jenisBarang?->nama_jenis_barang);
                    }),

                TextInput::make('ukuran_label')
                    ->label('Ukuran')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?MasukGrajiTriplek $record) {
                        $serah = $record?->serahTerimaHp;

                        if (! $serah) {
                            return;
                        }

                        if ($serah->id_triplek_mth_mutasi_keluar !== null) {
                            $m = $serah->triplekMthMutasiKeluar;
                            $set('ukuran_label', $m
                                ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0)
                                : null);

                            return;
                        }

                        if (! $record?->barangSetengahJadiHp) {
                            return;
                        }

                        $set('ukuran_label', $record->barangSetengahJadiHp->ukuran?->nama_ukuran);
                    }),

                TextInput::make('no_palet')
                    ->label('Nomor Palet (Sumber)')
                    ->disabled()
                    ->dehydrated(),
            ]);
    }

    protected static function getPaletOptions(?MasukGrajiTriplek $record): array
    {
        $currentId = $record?->id_serah_terima_hp;
        $currentIsi = (float) ($record?->isi ?? 0);

        return SerahTerimaHp::query()
            ->where('diterima_oleh', '!=', '-')
            ->where('tujuan', 'graji_triplek')
            // Dibatasi EKSPLISIT hanya ke sumber yang didukung form ini:
            // Hotpress (id_triplek_hasil_hp) ATAU Gudang Triplek Mentah
            // (id_triplek_mth_mutasi_keluar). Sengaja TIDAK ikut menarik
            // id_hasil_sanding (serah manual Sanding -> Graji) supaya barang
            // dari sumber yang belum tentu dimaksudkan bisa dipakai di sini
            // tidak tiba-tiba muncul di dropdown.

            ->with(self::HASIL_RELATIONS)
            ->get()
            ->map(function ($item) use ($currentId, $currentIsi) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentIsi : 0);

                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$item, $sisa] = $pair;
                $sisaLabel = rtrim(rtrim(number_format($sisa, 2, '.', ''), '0'), '.');

                // Barang dari Gudang Triplek Mentah: rakit label dari mutasi
                // keluar (tidak punya no_palet / barangSetengahJadi).
                if ($item->id_triplek_mth_mutasi_keluar !== null) {
                    $m = $item->triplekMthMutasiKeluar;

                    $ukuranLabel = $m
                        ? ($m->panjang + 0).' x '.($m->lebar + 0).' x '.($m->tebal + 0)
                        : '-';
                    $jenis = strtoupper($m?->jenisKayu?->nama_kayu ?? '-');
                    $gradeLabel = $m?->kw_grade ?? '-';

                    // 🌟 IDENTITAS disamakan gaya dengan sumber lama ("Palet {no}"):
                    // tanpa tanda pagar/tanggal, cukup nomor mutasi.
                    $identitas = 'TM'.$item->id_triplek_mth_mutasi_keluar;

                    $label = "{$identitas} - {$ukuranLabel} {$jenis} {$gradeLabel}"
                        ." — Sisa: {$sisaLabel} (triplek mentah)";

                    return [$item->id => $label];
                }

                // Sumber Hotpress (triplekHasilHp).
                $hasil = $item->triplekHasilHp;
                $barang = $hasil?->barangSetengahJadi;
                $ukuran = $barang?->ukuran;

                $ukuranLabel = $ukuran
                    ? "{$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal}"
                    : '-';
                $kodeJenisBarang = strtoupper($barang?->jenisBarang?->kode_jenis_barang ?? '-');
                $gradeLabel = $barang?->grade?->nama_grade ?? '-';

                $label = "Palet {$hasil?->no_palet} - {$ukuranLabel} {$kodeJenisBarang} {$gradeLabel} — Sisa: {$sisaLabel}";

                return [$item->id => $label];
            })
            ->toArray();
    }
}
