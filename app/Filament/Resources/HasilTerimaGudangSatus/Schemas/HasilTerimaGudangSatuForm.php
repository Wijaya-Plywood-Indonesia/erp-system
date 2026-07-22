<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Schemas;

use App\Models\BahanTerimaGudangSatu;
use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\SerahTerimaGudangSatu;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HasilTerimaGudangSatuForm
{
    /**
     * Label opsi untuk satu baris serah terima, sadar-asal:
     *  - Triplek Jadi : dirakit dari relasi triplekMutasiKeluar.
     *  - Pilih Plywood: format lama, tidak berubah.
     */
    protected static function labelOpsiBahan(SerahTerimaGudangSatu $s): string
    {
        $sisa = rtrim(rtrim(number_format($s->sisa, 2, ',', '.'), '0'), ',');

        if ($s->id_triplek_mutasi_keluar !== null) {
            $m = $s->triplekMutasiKeluar;

            return 'Plywood | '.
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

    /**
     * Resolusi id_jenis_barang, id_ukuran, id_grade_asal, & id_barang_setengah_jadi_hp
     * dari satu baris SerahTerimaGudangSatu, apa pun sumbernya (Triplek Jadi atau
     * Pilih Plywood).
     *
     * Untuk Triplek Jadi: jenis barang & ukuran dicari lewat pencocokan
     * atribut (nama_kayu, panjang/lebar/tebal) ke tabel jenis_barang &
     * ukuran. kw_grade (string) dicocokkan ke Grade.nama_grade untuk
     * mendapatkan id_grade relasional. Setelah itu, kombinasi
     * jenis+ukuran+grade dicari di BarangSetengahJadiHp; kalau belum ada
     * baris yang cocok, baris baru dibuat otomatis (find-or-create).
     */
    protected static function resolveJenisUkuranGradeAsal(?SerahTerimaGudangSatu $s): array
    {
        if (! $s) {
            return [
                'id_jenis_barang' => null,
                'id_ukuran' => null,
                'id_grade_asal' => null,
                'id_barang_setengah_jadi_hp' => null,
                'grade_asal_info' => '-',
            ];
        }

        if ($s->id_triplek_mutasi_keluar !== null) {
            $m = $s->triplekMutasiKeluar;

            $ukuran = Ukuran::where('panjang', $m?->panjang)
                ->where('lebar', $m?->lebar)
                ->where('tebal', $m?->tebal)
                ->first();
            $idUkuran = $ukuran?->id;

            $jenisBarang = JenisBarang::where('nama_jenis_barang', $m?->jenisKayu?->nama_kayu)->first();
            $idJenisBarang = $jenisBarang?->id;

            // kw_grade dari Triplek Mutasi Keluar berupa string; coba
            // cocokkan ke Grade.nama_grade supaya kita punya id_grade
            // relasional juga (dibutuhkan oleh BarangSetengahJadiHp).
            $grade = $m?->kw_grade
                ? Grade::where('nama_grade', $m->kw_grade)->first()
                : null;
            $idGrade = $grade?->id;

            // Kalau jenis & ukuran berhasil di-resolve, pastikan ada baris
            // BarangSetengahJadiHp yang merepresentasikan kombinasi ini.
            // Kalau belum ada (mis. bahan ini baru pertama kali muncul dari
            // Triplek Jadi), buat baru otomatis.
            $barangSetengahJadiHp = null;
            if ($idJenisBarang && $idUkuran) {
                $barangSetengahJadiHp = BarangSetengahJadiHp::firstOrCreate([
                    'id_jenis_barang' => $idJenisBarang,
                    'id_ukuran' => $idUkuran,
                    'id_grade' => $idGrade,
                ]);
            }

            return [
                'id_jenis_barang' => $idJenisBarang,
                'id_ukuran' => $idUkuran,
                'id_grade_asal' => $idGrade,
                'id_barang_setengah_jadi_hp' => $barangSetengahJadiHp?->id,
                'grade_asal_info' => $m?->kw_grade ?? '-',
            ];
        }

        $b = $s->barangSetengahJadi;

        return [
            'id_jenis_barang' => $b?->id_jenis_barang,
            'id_ukuran' => $b?->id_ukuran,
            'id_grade_asal' => $b?->id_grade,
            'id_barang_setengah_jadi_hp' => $b?->id,
            'grade_asal_info' => $b?->grade?->nama_grade ?? '-',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ─────────────────────────────────────────
                // BAHAN (dipilih dari serah terima, bebas — tidak dilock ke sisa)
                // ─────────────────────────────────────────
                Section::make('Bahan yang Dipakai')
                    ->schema([
                        Select::make('bahan.id_serah_terima_gudang_satu')
                            ->label('Bahan (Serah Terima Gudang Satu)')
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->options(function (callable $get) {
                                $currentId = $get('bahan.id_serah_terima_gudang_satu');

                                // Pastikan hanya bahan yang SUDAH DITERIMA dan tujuannya
                                // memang gudang_satu yang muncul (kategori & sisa bebas,
                                // tidak difilter).
                                return SerahTerimaGudangSatu::query()
                                    ->with([
                                        'hasilPilihPlywood.barangSetengahJadiHp.ukuran',
                                        'hasilPilihPlywood.barangSetengahJadiHp.jenisBarang',
                                        'hasilPilihPlywood.barangSetengahJadiHp.grade.kategoriBarang',
                                        'triplekMutasiKeluar.jenisKayu',
                                        'hasilNyusup.barangSetengahJadiHp.ukuran',
                                        'hasilNyusup.barangSetengahJadiHp.jenisBarang',
                                        'hasilNyusup.barangSetengahJadiHp.grade.kategoriBarang',
                                        'hasilTerimaGudangSatu.ukuran',
                                        'hasilTerimaGudangSatu.jenisBarang',
                                        'hasilTerimaGudangSatu.grade.kategoriBarang',
                                    ])
                                    ->where('status', 'Diterima')
                                    ->where('tujuan', 'gudang_satu')
                                    ->get()
                                    // Simple: qty asli dikurangi total pemakaian (accessor
                                    // `sisa` di model). Tampilkan jika sisanya > 0 ATAU merupakan ID yang sedang diedit.
                                    ->filter(fn ($s) => $s->sisa > 0 || $s->id == $currentId)
                                    ->sortBy(fn ($s) => $s->id_triplek_mutasi_keluar !== null
                                        ? (float) ($s->triplekMutasiKeluar?->tebal ?? 0)
                                        : (float) ($s->barangSetengahJadi?->ukuran?->tebal ?? 0))
                                    ->mapWithKeys(fn ($s) => [$s->id => static::labelOpsiBahan($s)]);
                            })
                            // Saat bahan dipilih: ambil info sisa + auto-set jenis barang,
                            // ukuran, grade asal, & id_barang_setengah_jadi_hp (grade
                            // tujuan tetap dipilih manual di bawah, karena ini proses
                            // turun grade). Menangani KEDUA sumber: Triplek Jadi maupun
                            // Pilih Plywood.
                            ->afterStateUpdated(function ($state, callable $set) {
                                $s = $state ? SerahTerimaGudangSatu::find($state) : null;

                                $set('bahan.sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');

                                $resolved = static::resolveJenisUkuranGradeAsal($s);

                                $set('id_jenis_barang', $resolved['id_jenis_barang']);
                                $set('id_ukuran', $resolved['id_ukuran']);
                                $set('id_grade_asal', $resolved['id_grade_asal']);
                                $set('id_barang_setengah_jadi_hp', $resolved['id_barang_setengah_jadi_hp']);
                                // Tabel bahan_terima_gudang_satu juga butuh kolom ini,
                                // jadi harus di-set dengan prefix bahan.* juga.
                                $set('bahan.id_barang_setengah_jadi_hp', $resolved['id_barang_setengah_jadi_hp']);
                                $set('grade_asal_info', $resolved['grade_asal_info']);
                            })
                            ->afterStateHydrated(function (callable $set, callable $get) {
                                $id = $get('bahan.id_serah_terima_gudang_satu');
                                $s = $id ? SerahTerimaGudangSatu::find($id) : null;
                                $set('bahan.sisa_info', $s ? number_format($s->sisa, 2, ',', '.') : '-');

                                // Pastikan hidden fields juga terisi saat form di-hydrate
                                // ulang (mis. saat edit record), bukan hanya saat user
                                // baru saja memilih di dropdown.
                                $resolved = static::resolveJenisUkuranGradeAsal($s);

                                $set('id_jenis_barang', $get('id_jenis_barang') ?? $resolved['id_jenis_barang']);
                                $set('id_ukuran', $get('id_ukuran') ?? $resolved['id_ukuran']);
                                $set('id_grade_asal', $get('id_grade_asal') ?? $resolved['id_grade_asal']);
                                $set('id_barang_setengah_jadi_hp', $get('id_barang_setengah_jadi_hp') ?? $resolved['id_barang_setengah_jadi_hp']);
                                $set('bahan.id_barang_setengah_jadi_hp', $get('bahan.id_barang_setengah_jadi_hp') ?? $resolved['id_barang_setengah_jadi_hp']);
                                $set('grade_asal_info', $resolved['grade_asal_info']);
                            }),

                        Hidden::make('bahan.id_barang_setengah_jadi_hp'),

                        TextInput::make('bahan.sisa_info')
                            ->label('Sisa Tersedia (info saja)')
                            ->disabled()
                            ->dehydrated(false)
                            ->reactive(),

                        TextInput::make('grade_asal_info')
                            ->label('Grade Asal (info saja)')
                            ->disabled()
                            ->dehydrated(false)
                            ->reactive(),

                        TextInput::make('bahan.no_palet')
                            ->label('No Palet')
                            ->numeric()
                            ->required()
                            ->default(fn () => BahanTerimaGudangSatu::count() + 1),

                        TextInput::make('bahan.jumlah')
                            ->label('Jumlah Dikerjakan Hari Ini')
                            ->numeric()
                            ->required()
                            ->reactive()
                            ->minValue(0.01)
                            // Catatan: tidak ada maxValue/lock ke sisa serah terima —
                            // jumlah bebas diisi sesuai kebutuhan lapangan.
                            ->afterStateUpdated(fn ($state, callable $set) => $set('jumlah', $state)),
                    ])
                    ->columnSpanFull(),

                // ─────────────────────────────────────────
                // HASIL — turun grade: jenis barang & ukuran ikut bahan asal,
                // yang dipilih manual hanya Grade tujuan (grade baru/turunan).
                // Jumlah otomatis ikut "Jumlah Dikerjakan Hari Ini" di atas.
                // ─────────────────────────────────────────
                Section::make('Hasil Produksi (Turun Grade)')
                    ->schema([
                        Hidden::make('id_jenis_barang')
                            ->required()
                            ->validationMessages([
                                'required' => 'Jenis barang tidak dapat ditentukan otomatis dari bahan yang dipilih. Silakan periksa data bahan (khususnya untuk sumber Triplek Jadi).',
                            ]),
                        Hidden::make('id_ukuran')
                            ->required()
                            ->validationMessages([
                                'required' => 'Ukuran tidak dapat ditentukan otomatis dari bahan yang dipilih. Silakan periksa data bahan (khususnya untuk sumber Triplek Jadi).',
                            ]),
                        Hidden::make('id_grade_asal'),
                        Hidden::make('id_barang_setengah_jadi_hp'),

                        Select::make('id_grade')
                            ->label('Simpan Sebagai Grade')
                            ->required()
                            ->searchable()
                            ->options(
                                Grade::whereHas('kategoriBarang', function ($q) {
                                    $q->where('nama_kategori', 'PLYWOOD');
                                })
                                    ->orderBy('nama_grade')
                                    ->pluck('nama_grade', 'id')
                            )
                            ->helperText('Pilih grade baru hasil penurunan dari grade asal di atas.'),

                        TextInput::make('jumlah')
                            ->label('Jumlah')
                            ->numeric()
                            ->required()
                            ->disabled()
                            ->dehydrated(true)
                            ->reactive()
                            ->afterStateHydrated(function (callable $set, callable $get) {
                                $set('jumlah', $get('bahan.jumlah'));
                            }),

                        Textarea::make('ket')
                            ->label('Keterangan')
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
