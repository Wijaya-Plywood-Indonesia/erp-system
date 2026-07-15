<?php

namespace App\Filament\Resources\BahanTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\SerahTerimaGudangSatu;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BahanTerimaGudangSatuForm
{
+    /**
     * Label opsi untuk satu baris serah terima, sadar-asal:
     *  - Triplek Jadi : dirakit dari relasi triplekMutasiKeluar.
     *  - Pilih Plywood: format lama, tidak berubah.
     */
    protected static function labelOpsi(SerahTerimaGudangSatu $s): string
    {
        $sisa = rtrim(rtrim(number_format($s->sisa, 2, ',', '.'), '0'), ',');

        if ($s->id_triplek_mutasi_keluar !== null) {
            $m = $s->triplekMutasiKeluar;

            return 'TRIPLEK JADI | '.
                ($m ? ($m->panjang + 0).'×'.($m->lebar + 0).'×'.($m->tebal + 0) : '-').' | '.
                ($m?->kw_grade ?? '-').' | '.
                ($m?->jenisKayu?->nama_kayu ?? '-').
                ' (Sisa: '.$sisa.')';
        }

        $b = $s->barangSetengahJadi;

        return ($b?->grade?->kategoriBarang?->nama_kategori ?? '-').' | '.
            ($b?->ukuran?->nama_ukuran ?? '-').' | '.
            ($b?->grade?->nama_grade ?? '-').' | '.
            ($b?->jenisBarang?->nama_jenis_barang ?? '-').
            ' (Sisa: '.$sisa.')';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_serah_terima_gudang_satu')
                    ->label('Bahan (Serah Terima Gudang Satu)')
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->options(function (callable $get) {
                        $query = SerahTerimaGudangSatu::query()
                            ->with([
                                'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                                'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                                'triplekMutasiKeluar.jenisKayu',
                            ])
                            ->where('diterima_oleh', '!=', '-')
                            // 🌟 Dua asal bahan yang sah:
                            //  (a) Pilih Plywood berkategori PLYWOOD (syarat lama), ATAU
                            //  (b) keluaran Gudang Triplek Jadi (baris punya
                            //      id_triplek_mutasi_keluar). whereHas lama menyaring
                            //      habis baris triplek karena hasilPilihPlywood-nya NULL.
                            ->where(function ($q) {
                                $q->whereNotNull('id_triplek_mutasi_keluar')
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
                            ->sortBy(fn ($s) => $s->id_triplek_mutasi_keluar !== null
                                ? (float) ($s->triplekMutasiKeluar?->tebal ?? 0)
                                : (float) ($s->barangSetengahJadi?->ukuran?->tebal ?? 0))
                            ->mapWithKeys(fn ($s) => [$s->id => static::labelOpsi($s)]);
                    })
                    ->afterStateUpdated(function ($state, callable $set) {
                        // reset jumlah biar tidak kebawa nilai lama yang mungkin melebihi sisa baru
                        $set('jumlah', null);

                        $s = $state ? SerahTerimaGudangSatu::find($state) : null;
                        $set('sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');
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
                    ->maxValue(function (callable $get) {
                        $id = $get('id_serah_terima_gudang_satu');
                        if (! $id) {
                            return null;
                        }
                        $s = SerahTerimaGudangSatu::find($id);

                        return $s?->sisa;
                    }),
            ]);
    }
}