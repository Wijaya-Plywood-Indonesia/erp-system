<?php

namespace App\Filament\Resources\HasilSandings\Schemas;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HasilSandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (HANYA PLATFORM & PLYWOOD) - DISABLED
                |--------------------------------------------------------------------------
                */
                // Select::make('grade_id')
                //     ->label('Grade')
                //     ->options(
                //         Grade::with('kategoriBarang')
                //             ->whereHas('kategoriBarang', function ($q) {
                //                 $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
                //             })
                //             ->get()
                //             ->mapWithKeys(fn($g) => [
                //                 $g->id => ($g->kategoriBarang?->nama_kategori ?? '-') .
                //                     ' - ' .
                //                     $g->nama_grade
                //             ])
                //     )
                //     ->reactive()
                //     ->searchable()
                //     ->placeholder('Semua Grade'),

                /*
                |--------------------------------------------------------------------------
                | FILTER JENIS BARANG - DISABLED
                |--------------------------------------------------------------------------
                */
                // Select::make('id_jenis_barang')
                //     ->label('Jenis Barang')
                //     ->options(
                //         JenisBarang::orderBy('nama_jenis_barang')
                //             ->pluck('nama_jenis_barang', 'id')
                //     )
                //     ->reactive()
                //     ->searchable()
                //     ->placeholder('Semua Jenis Barang'),

                /*
                |--------------------------------------------------------------------------
                | BARANG SETENGAH JADI
                |--------------------------------------------------------------------------
                */
                Select::make('id_barang_setengah_jadi')
                    ->label('Modal')
                    ->required()

                    // OPTIONS SAAT CREATE
                    ->options(function (callable $get, $livewire, ?\App\Models\HasilSanding $record) {

                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])

                            // 🔒 HANYA PLATFORM & PLYWOOD
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->whereIn('nama_kategori', ['PLATFORM', 'PLYWOOD']);
                            });

                        // 🔒 HANYA YANG DI INPUT DI MODAL SANDING (DAN SISANYA > 0)
                        $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                        
                        $sisaPerBarang = [];

                        if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiSanding) {
                            $modalBarang = \App\Models\ModalSanding::where('id_produksi_sanding', $ownerRecord->id)
                                ->whereNotNull('id_barang_setengah_jadi')
                                ->selectRaw('id_barang_setengah_jadi, sum(kuantitas) as total_modal')
                                ->groupBy('id_barang_setengah_jadi')
                                ->get();

                            $hasilBarangQuery = \App\Models\HasilSanding::where('id_produksi_sanding', $ownerRecord->id)
                                ->whereNotNull('id_barang_setengah_jadi');
                            
                            if ($record) {
                                $hasilBarangQuery->where('id', '!=', $record->id);
                            }

                            $hasilBarang = $hasilBarangQuery
                                ->selectRaw('id_barang_setengah_jadi, sum(kuantitas) as total_hasil')
                                ->groupBy('id_barang_setengah_jadi')
                                ->pluck('total_hasil', 'id_barang_setengah_jadi');

                            $availableIds = [];
                            foreach ($modalBarang as $mb) {
                                $totalHasil = $hasilBarang[$mb->id_barang_setengah_jadi] ?? 0;
                                $sisa = $mb->total_modal - $totalHasil;
                                if ($sisa > 0) {
                                    $availableIds[] = $mb->id_barang_setengah_jadi;
                                    $sisaPerBarang[$mb->id_barang_setengah_jadi] = $sisa;
                                }
                            }
                            
                            // Pastikan record yang sedang diedit tetap muncul di opsi
                            if ($record && $record->id_barang_setengah_jadi && !in_array($record->id_barang_setengah_jadi, $availableIds)) {
                                $availableIds[] = $record->id_barang_setengah_jadi;
                                // Hitung sisa khusus untuk record yang sedang diedit
                                $totalModalEdit = \App\Models\ModalSanding::where('id_produksi_sanding', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi', $record->id_barang_setengah_jadi)
                                    ->sum('kuantitas');
                                $totalHasilEdit = $hasilBarang[$record->id_barang_setengah_jadi] ?? 0;
                                $sisaPerBarang[$record->id_barang_setengah_jadi] = max(0, $totalModalEdit - $totalHasilEdit);
                            }
                            
                            $query->whereIn('id', $availableIds);
                        }

                        // if ($get('grade_id')) {
                        //     $query->where('id_grade', $get('grade_id'));
                        // }

                        // if ($get('id_jenis_barang')) {
                        //     $query->where('id_jenis_barang', $get('id_jenis_barang'));
                        // }

                        // if (!$get('grade_id') && !$get('id_jenis_barang')) {
                        //     $query->limit(50);
                        // }

                        return $query
                            ->orderBy('id', 'desc')
                            ->get()
                            ->mapWithKeys(function ($b) use ($sisaPerBarang) {

                                $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                                $ukuran   = $b->ukuran?->dimensi ?? '-';
                                $grade    = $b->grade?->nama_grade ?? '-';
                                $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';
                                
                                $sisaInfo = isset($sisaPerBarang[$b->id]) ? " · {$sisaPerBarang[$b->id]} lbr" : '';

                                return [
                                    $b->id => "{$kategori} · {$ukuran} · {$grade} · {$jenis}{$sisaInfo}"
                                ];
                            });
                    })

                    // LABEL SAAT EDIT
                    ->getOptionLabelUsing(function ($value) {

                        $b = BarangSetengahJadiHp::with([
                            'ukuran',
                            'jenisBarang',
                            'grade.kategoriBarang'
                        ])->find($value);

                        if (!$b) {
                            return $value;
                        }

                        $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran   = $b->ukuran?->dimensi ?? '-';
                        $grade    = $b->grade?->nama_grade ?? '-';
                        $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} — {$ukuran} — {$grade} — {$jenis}";
                    })

                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, $livewire, ?\App\Models\HasilSanding $record) {
                        if (!$state) {
                            $set('kuantitas', null);
                            return;
                        }

                        $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                        if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiSanding) {
                            $modalTotal = \App\Models\ModalSanding::where('id_produksi_sanding', $ownerRecord->id)
                                ->where('id_barang_setengah_jadi', $state)
                                ->sum('kuantitas');

                            $hasilTotalQuery = \App\Models\HasilSanding::where('id_produksi_sanding', $ownerRecord->id)
                                ->where('id_barang_setengah_jadi', $state);
                            
                            if ($record) {
                                $hasilTotalQuery->where('id', '!=', $record->id);
                            }

                            $hasilTotal = $hasilTotalQuery->sum('kuantitas');

                            $sisa = $modalTotal - $hasilTotal;
                            $set('kuantitas', max(0, $sisa));
                        }
                    })
                    ->placeholder('Pilih Barang'),

                /*
                |--------------------------------------------------------------------------
                | INPUT DATA
                |--------------------------------------------------------------------------
                */
                TextInput::make('kuantitas')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn (callable $get, $livewire, ?\App\Models\HasilSanding $record) => function (string $attribute, $value, \Closure $fail) use ($get, $livewire, $record) {
                            $idBarang = $get('id_barang_setengah_jadi');
                            if (!$idBarang) return;

                            $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                            if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiSanding) {
                                $modalTotal = \App\Models\ModalSanding::where('id_produksi_sanding', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi', $idBarang)
                                    ->sum('kuantitas');

                                $hasilTotalQuery = \App\Models\HasilSanding::where('id_produksi_sanding', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi', $idBarang);
                                
                                if ($record) {
                                    $hasilTotalQuery->where('id', '!=', $record->id);
                                }

                                $hasilTotal = $hasilTotalQuery->sum('kuantitas');
                                $sisa = $modalTotal - $hasilTotal;

                                if ($value > $sisa) {
                                    $fail("Kuantitas melebihi sisa yang tersedia dari Modal Sanding ({$sisa}).");
                                }
                            }
                        },
                    ]),

                TextInput::make('jumlah_sanding_face')
                    ->label('Jumlah Sanding Face (Pass)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('jumlah_sanding_back')
                    ->label('Jumlah Sanding Back (Pass)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                TextInput::make('no_palet')
                    ->numeric()
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'Selesai 1 Sisi' => 'Selesai 1 Sisi',
                        'Selesai 2 Sisi' => 'Selesai 2 Sisi',
                        'Belum Selesai'  => 'Belum Selesai',
                    ])
                    ->default('Belum Selesai')
                    ->required(),
            ]);
    }
}
