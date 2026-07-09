<?php

namespace App\Filament\Resources\BahanPilihPlywoods\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use Filament\Forms\Get;
use App\Models\JenisBarang;
use App\Models\SerahTerimaTriplekJadi;

class BahanPilihPlywoodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                |--------------------------------------------------------------------------
                | PILIH PALET OTOMATIS (DARI SERAH TERIMA YANG SUDAH DITERIMA)
                |--------------------------------------------------------------------------
                | Ketika dipilih, semua field di bawahnya akan terisi otomatis.
                */
                Select::make('id_serah_terima_triplek_jadi')
                    ->label('⚡ Pilih dari Palet yang Diterima (Auto-Fill)')
                    ->placeholder('-- Pilih Palet / Atau Abaikan untuk Input Manual di Bawah --')
                    ->options(function ($livewire) {
                        // Ambil ID Produksi Pilih Plywood yang sedang dibuka
                        $ownerId = $livewire->ownerRecord?->id;
                        
                        if (! $ownerId) {
                            return [];
                        }

                        // Hanya tampilkan palet yang sudah DITERIMA pada produksi hari ini
                        return SerahTerimaTriplekJadi::with([
                                'hasilSanding.barangSetengahJadi.jenisBarang',
                                'hasilSanding.barangSetengahJadi.grade',
                                'hasilGrajiTriplek.barangSetengahJadiHp.jenisBarang',
                                'hasilGrajiTriplek.barangSetengahJadiHp.grade',
                            ])
                            ->where('id_produksi_pilih_plywood', $ownerId)
                            ->where('diterima_oleh', '!=', '-') // Pastikan statusnya sudah diterima
                            ->get()
                            ->mapWithKeys(function ($item) {
                                $noPalet = $item->hasil?->no_palet ?? '-';
                                $bsj     = $item->barang_setengah_jadi;
                                $jenis   = $bsj?->jenisBarang?->nama_jenis_barang ?? 'Plywood';
                                $grade   = $bsj?->grade?->nama_grade ?? '-';
                                $sisa    = number_format($item->sisa);
                                $total   = number_format($item->qty_asli);

                                // Format tampilan di dropdown: Palet #2 — MERANTI (Grade FM) — Sisa: 1,243/1,243 Lbr
                                return [
                                    $item->id => "Palet #{$noPalet} — {$jenis} (Grade {$grade}) — Sisa: {$sisa} / {$total} Lbr"
                                ];
                            });
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (! $state) {
                            return;
                        }

                        // Ambil data detail dari palet yang dipilih
                        $item = SerahTerimaTriplekJadi::with([
                            'hasilSanding.barangSetengahJadi',
                            'hasilGrajiTriplek.barangSetengahJadiHp',
                        ])->find($state);

                        if (! $item) {
                            return;
                        }

                        $bsj   = $item->barang_setengah_jadi;
                        $hasil = $item->hasil;

                        if ($bsj) {
                            // 1. Set Filter Grade & Jenis Barang terlebih dahulu 
                            //    (PENTING: agar opsi barang setengah jadi di bawahnya bisa muncul/tervalidasi)
                            $set('grade_id', $bsj->id_grade);
                            $set('jenis_barang_id_filter', $bsj->id_jenis_barang);
                            
                            // 2. Set ID Barang Setengah Jadi
                            $set('id_barang_setengah_jadi_hp', $bsj->id);
                        }

                        if ($hasil) {
                            // 3. Set Nomor Palet
                            $set('no_palet', $hasil->no_palet);
                        }

                        // 4. Set Jumlah (Gunakan nilai SISA agar tidak melebihi stok yang ada)
                        $set('jumlah', $item->sisa > 0 ? $item->sisa : $item->qty_asli);
                    })
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
                */
                /*
                |--------------------------------------------------------------------------
                | FILTER GRADE (DENGAN KATEGORI)
                |--------------------------------------------------------------------------
                */
                Select::make('grade_id')
                    ->label('Filter Grade')
                    ->options(
                        Grade::whereHas('kategoriBarang', function ($q) {
                            $q->where('nama_kategori', 'PLYWOOD');
                        })
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' . $g->nama_grade
                            ])
                    )
                    // Tambahkan baris ini agar label yang benar muncul saat auto-fill
                    ->getOptionLabelUsing(function ($value) {
                        $g = Grade::with('kategoriBarang')->find($value);
                        if (! $g) return '-';
                        
                        return ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori') . ' | ' . $g->nama_grade;
                    })
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                Select::make('jenis_barang_id_filter')
                    ->label('Filter Jenis Barang')
                    ->options(
                        JenisBarang::orderBy('nama_jenis_barang')
                            ->pluck('nama_jenis_barang', 'id')
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Jenis Barang')
                    ->dehydrated(false),

                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Barang Setengah Jadi (Plywood)')
                    ->required()
                    ->searchable()
                    // Menggunakan getSearchResultsUsing dan getOptionLabelUsing
                    // adalah cara terbaik di Filament untuk custom dropdown yang kompleks
                    ->options(function (callable $get) {
                        $query = BarangSetengahJadiHp::query()
                            ->with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            });

                        // Terapkan filter jika ada
                        if ($get('grade_id')) {
                            $query->where('id_grade', $get('grade_id'));
                        }
                        if ($get('jenis_barang_id_filter')) {
                            $query->where('id_jenis_barang', $get('jenis_barang_id_filter'));
                        }

                        return $query->get()->mapWithKeys(function ($b) {
                            return [
                                $b->id => ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                                    ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                                    ($b->grade?->nama_grade ?? '-') . ' | ' .
                                    ($b->jenisBarang?->nama_jenis_barang ?? '-')
                            ];
                        });
                    })
                    // Tambahkan ini agar Filament tahu cara merender label saat state diubah via Set
                    ->getOptionLabelUsing(function ($value) {
                        $b = BarangSetengahJadiHp::with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])->find($value);
                        
                        if (! $b) return '-';

                        return ($b->grade?->kategoriBarang?->nama_kategori ?? '-') . ' | ' .
                               ($b->ukuran?->nama_ukuran ?? '-') . ' | ' .
                               ($b->grade?->nama_grade ?? '-') . ' | ' .
                               ($b->jenisBarang?->nama_jenis_barang ?? '-');
                    })
                    ->columnSpanFull(),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->required()
                    ->live(debounce: 500)
                    // UBAH BAGIAN DI BAWAH INI:
                    ->hint(function (callable $get) {
                        $idSerahTerima = $get('id_serah_terima_triplek_jadi');
                        $inputJumlah = (float) ($get('jumlah') ?? 0);

                        if (! $idSerahTerima) return null;

                        $serahTerima = \App\Models\SerahTerimaTriplekJadi::find($idSerahTerima);
                        if (! $serahTerima) return null;

                        $sisaAkhir = $serahTerima->sisa - $inputJumlah;

                        return "Sisa tersedia: {$sisaAkhir} Lbr";
                    })
                    ->hintColor(function (callable $get) {
                        $idSerahTerima = $get('id_serah_terima_triplek_jadi');
                        if (! $idSerahTerima) return 'gray';

                        $serahTerima = \App\Models\SerahTerimaTriplekJadi::find($idSerahTerima);
                        if (! $serahTerima) return 'gray';

                        $sisaAkhir = $serahTerima->sisa - (float) ($get('jumlah') ?? 0);

                        return $sisaAkhir < 0 ? 'danger' : 'success';
                    })
            ]);
    }
}