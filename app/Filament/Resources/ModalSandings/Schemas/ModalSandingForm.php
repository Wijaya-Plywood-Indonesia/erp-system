<?php

namespace App\Filament\Resources\ModalSandings\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\ModalSanding;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ModalSandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            /*
            |--------------------------------------------------------------------------
            | FILTER GRADE
            |--------------------------------------------------------------------------
            */
            Select::make('grade_id')
    ->label('Grade')
    ->default(fn(callable $get) => self::lastValue($get, 'grade_id'))
    ->options(
        Grade::with('kategoriBarang')
            ->whereHas('kategoriBarang', function ($q) {
                $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
            })
            ->get()
            ->mapWithKeys(fn($g) => [
                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                    . ' - ' . $g->nama_grade
            ])
    )
    ->reactive()
    ->searchable()
    ->placeholder('Semua Grade'),


            /*
            |--------------------------------------------------------------------------
            | FILTER JENIS BARANG
            |--------------------------------------------------------------------------
            */
            Select::make('id_jenis_barang')
                ->label('Jenis Barang')
                ->default(fn(callable $get) => self::lastValue($get, 'id_jenis_barang'))
                ->options(JenisBarang::pluck('nama_jenis_barang', 'id'))
                ->reactive()
                ->searchable()
                ->placeholder('Semua Jenis Barang'),

            /*
            |--------------------------------------------------------------------------
            | BARANG SETENGAH JADI (TERGANTUNG FILTER)
            |--------------------------------------------------------------------------
            */
            Select::make('id_barang_setengah_jadi')
                ->label('Barang Setengah Jadi')

                // OPTIONS saat create / filter
                ->options(function (callable $get) {
                    $query = BarangSetengahJadiHp::query()
    ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
    ->whereHas('grade.kategoriBarang', function ($q) {
        $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
    });


                    if ($get('grade_id')) {
                        $query->where('id_grade', $get('grade_id'));
                    }

                    if ($get('jenis_barang_id')) {
                        $query->where('id_jenis_barang', $get('jenis_barang_id'));
                    }

                    if (!$get('grade_id') && !$get('jenis_barang_id')) {
                        $query->limit(50);
                    }

                    return $query->orderBy('id', 'desc')
                        ->get()
                        ->mapWithKeys(function ($b) {

                            $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? 'Kategori?';
                            $ukuran = $b->ukuran?->dimensi ?? 'Ukuran?';
                            $jenis = $b->jenisBarang?->nama_jenis_barang ?? 'Jenis?';
                            $grade = $b->grade?->nama_grade ?? 'Grade?';

                            return [
                                $b->id => "{$kategori} — {$ukuran} — {$grade} — {$jenis}"
                            ];
                        });
                })

                // LABEL saat EDIT (ini yang kamu butuhkan!)
                ->getOptionLabelUsing(function ($value) {
                    $b = BarangSetengahJadiHp::with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
                        ->find($value);

                    if (!$b)
                        return $value; // fallback ID
        
                    $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? 'Kategori?';
                    $ukuran = $b->ukuran?->dimensi ?? 'Ukuran?';
                    $jenis = $b->jenisBarang?->nama_jenis_barang ?? 'Jenis?';
                    $grade = $b->grade?->nama_grade ?? 'Grade?';

                    return "{$kategori} — {$ukuran} — {$grade} — {$jenis}";
                })

                ->searchable()
                ->placeholder('Pilih Barang'),

            /*
            |--------------------------------------------------------------------------
            | KUANTITAS
            |--------------------------------------------------------------------------
            */
            TextInput::make('kuantitas')
                ->label('Kuantitas')
                ->numeric()
                ->minValue(1)
                ->default(fn(callable $get) => self::lastValue($get, 'kuantitas'))
                ->required(),

            /*
            |--------------------------------------------------------------------------
            | JUMLAH PASS SANDING
            |--------------------------------------------------------------------------
            */
            TextInput::make('jumlah_sanding_face')
                ->label('Jumlah Sanding Face (Pass)')
                ->numeric()
                ->minValue(1)
                ->default(fn(callable $get) => self::lastValue($get, 'jumlah_sanding'))
                ->required(),
            TextInput::make('jumlah_sanding_back')
                ->label('Jumlah Sanding Back (Pass)')
                ->numeric()
                ->minValue(1)
                ->default(fn(callable $get) => self::lastValue($get, 'jumlah_sanding'))
                ->required(),

            /*
            |--------------------------------------------------------------------------
            | NO PALET + VALIDASI UNIQUE
            |--------------------------------------------------------------------------
            */
            TextInput::make('no_palet')
                ->label('No Palet')
                ->numeric()
                ->required()
                ->rule(function ($livewire, $component) {
                    $parent = $livewire->getOwnerRecord(); // parent relation manager
        
                    // Ambil ID record yang sedang diedit dari state (Filament 4 way)
                    $editingId = $component->getState()['id'] ?? null;

                    return Rule::unique('modal_sandings', 'no_palet')
                        ->where('id_produksi_sanding', $parent->id)
                        ->ignore($editingId);
                }),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: membaca data terakhir per id_produksi_sanding
    |--------------------------------------------------------------------------
    */
    private static function lastValue(callable $get, string $column)
    {
        $idProduksi = $get('id_produksi_sanding');
        if (!$idProduksi) {
            return null;
        }

        return ModalSanding::where('id_produksi_sanding', $idProduksi)
            ->latest('id')
            ->value($column);
    }
}
