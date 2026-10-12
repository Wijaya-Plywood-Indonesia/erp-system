<?php

namespace App\Filament\Resources\BahanTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\SerahTerimaGudangSatu;
use App\Support\PaletSearch;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanTerimaGudangSatuForm
{
    /**
     * Label opsi untuk satu baris serah terima, sadar-asal:
     *  - Triplek Jadi : dirakit dari relasi triplekMutasiKeluar.
     *  - Pilih Plywood: format lama, tidak berubah.
     */
    protected static function labelOpsi(SerahTerimaGudangSatu $s): string
    {
        $sisa = rtrim(rtrim(number_format($s->sisa, 2, ',', '.'), '0'), ',');
        
        $noPalet = $s->hasilNyusup?->no_palet;
        $paletPrefix = $noPalet ? "Palet {$noPalet} - " : "";

        if ($s->id_triplek_mutasi_keluar !== null) {
            $m = $s->triplekMutasiKeluar;

            return $paletPrefix . 'TRIPLEK JADI | '.
                ($m ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0) : '-').' | '.
                ($m?->kw_grade ?? '-').' | '.
                ($m?->jenisKayu?->nama_kayu ?? '-').
                ' (Sisa: '.$sisa.')';
        }

        $b = $s->barangSetengahJadi;

        return $paletPrefix . ($b?->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
            ($b?->ukuran?->nama_ukuran ?? '-').' | '.
            ($b?->grade?->nama_grade ?? '-').' | '.
            ($b?->jenisBarang?->nama_jenis_barang ?? '-').
            ' (Sisa: '.$sisa.')';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                PaletSearch::configure(
                    Select::make('id_serah_terima_gudang_satu')
                        ->label('Bahan (Serah Terima Gudang Satu)'),
                    function (callable $get) {
                        $query = SerahTerimaGudangSatu::query()
                            ->with([
                                'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                                'triplekMutasiKeluar.jenisKayu',
                                'hasilNyusup',
                            ])
                            ->where('diterima_oleh', '!=', '-')
                            // 🌟 Dua asal bahan yang sah:
                            //  (a) Pilih Plywood berkategori PLYWOOD (syarat lama), ATAU
                            //  (b) keluaran Gudang Triplek Jadi (baris punya
                            //      id_triplek_mutasi_keluar). whereHas lama menyaring
                            //      habis baris triplek karena hasilPilihPlywood-nya NULL.
                            ->where(function ($q) {
                                $q->whereNotNull('id_triplek_mutasi_keluar')
                                    ->orWhereNotNull('id_hasil_nyusup')
                                    ->orWhereHas('hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang', function ($sub) {
                                        $sub->where('nama_kategori', 'PLYWOOD');
                                    });
                            });

                        // Filter grade/jenis barang (fitur lama, saat ini nonaktif di form)
                        // hanya relevan untuk jalur Pilih Plywood — baris triplek tetap
                        // ditampilkan agar tidak "hilang" saat filter dipakai.
                        if ($get('grade_id')) {
                            $query->where(function ($q) use ($get) {
                                $q->whereNotNull('id_triplek_mutasi_keluar')
                                    ->orWhereHas('hasilPilihPlywood.barangSetengahJadiHp', function ($sub) use ($get) {
                                        $sub->where('id_grade', $get('grade_id'));
                                    });
                            });
                        }

                        if ($get('jenis_barang_id_filter')) {
                            $query->where(function ($q) use ($get) {
                                $q->whereNotNull('id_triplek_mutasi_keluar')
                                    ->orWhereHas('hasilPilihPlywood.barangSetengahJadiHp', function ($sub) use ($get) {
                                        $sub->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                                    });
                            });
                        }

                        return $query
                            ->get()
                            // sisa dihitung via accessor (bukan kolom DB), jadi difilter di PHP
                            ->filter(fn ($s) => $s->sisa > 0)
                            ->map(function ($s) {
                                $triplek = $s->id_triplek_mutasi_keluar !== null;
                                $m = $s->triplekMutasiKeluar;
                                $b = $s->barangSetengahJadi;
                                $u = $b?->ukuran;

                                $ukuran = $triplek
                                    ? ($m ? ($m->panjang + 0).'x'.($m->lebar + 0).'x'.($m->tebal + 0) : '')
                                    : ($u ? ($u->panjang + 0).'x'.($u->lebar + 0).'x'.($u->tebal + 0) : '');
                                $tebal = $triplek ? (float) ($m?->tebal ?? 0) : (float) ($u?->tebal ?? 0);

                                return [
                                    'id' => $s->id,
                                    'label' => static::labelOpsi($s),
                                    'no' => (string) ($s->hasilNyusup?->no_palet ?? ''),
                                    'kategori' => $triplek ? 'Triplek Jadi' : ($b?->grade?->kategoriBarang?->nama_kategori ?? ''),
                                    'jenis' => $triplek ? ($m?->jenisKayu?->nama_kayu ?? '') : ($b?->jenisBarang?->nama_jenis_barang ?? ''),
                                    'grade' => $triplek ? ($m?->kw_grade ?? '') : ($b?->grade?->nama_grade ?? ''),
                                    'ukuran' => $ukuran,
                                    'asal' => $triplek ? 'triplek jadi' : ($s->id_hasil_nyusup !== null ? 'nyusup' : 'pilih plywood'),
                                    'urut' => $tebal, // urut tebal kecil -> besar (sama seperti sebelumnya)
                                ];
                            })
                            ->values()
                            ->all();
                    },
                )
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        // reset jumlah biar tidak kebawa nilai lama yang mungkin melebihi sisa baru
                        $set('jumlah', null);

                        $s = $state ? SerahTerimaGudangSatu::find($state) : null;
                        $set('sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');
                        
                        if ($s) {
                            $noPalet = $s->hasilNyusup?->no_palet;
                            if ($noPalet) {
                                $set('no_palet', $noPalet);
                            }
                            
                            if ($s->sisa > 0) {
                                $set('jumlah', $s->sisa);
                            }
                        }
                    }),

                TextInput::make('sisa_info')
                    ->label('Sisa Tersedia')
                    ->disabled()
                    ->dehydrated(false)
                    ->reactive()
                    ->afterStateHydrated(function (callable $set, callable $get) {
                        $id = $get('id_serah_terima_gudang_satu');
                        $s = $id ? SerahTerimaGudangSatu::find($id) : null;
                        $set('sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');
                    }),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required()
                    ->default(fn () => BahanTerimaGudangSatu::count() + 1),

                TextInput::make('jumlah')
                    ->label('Jumlah Dikerjakan Hari Ini')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->rules([
                        fn (callable $get, ?\App\Models\BahanTerimaGudangSatu $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idSerahTerima = $get('id_serah_terima_gudang_satu');
                            if (!$idSerahTerima) return;

                            $serahTerima = \App\Models\SerahTerimaGudangSatu::find($idSerahTerima);
                            if (!$serahTerima) return;

                            $sisa = $serahTerima->sisa;

                            if ($record && $record->id_serah_terima_gudang_satu === (int) $idSerahTerima) {
                                $sisa += (float) $record->jumlah;
                            }

                            if ($value > $sisa) {
                                $fail("Jumlah melebihi sisa yang tersedia dari palet ({$sisa} lbr).");
                            }
                        },
                    ]),
            ]);
    }
}