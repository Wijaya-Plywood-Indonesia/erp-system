<?php

namespace App\Filament\Resources\HasilGrajiTripleks\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\JenisBarang;
use App\Models\Grade;

class HasilGrajiTriplekForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('id_barang_setengah_jadi_hp')
                    ->label('Modal')
                    ->required()
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set, $livewire, ?\App\Models\HasilGrajiTriplek $record) {
                        if (!$state) {
                            $set('isi', null);
                            return;
                        }

                        $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                        if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiGrajitriplek) {
                            $modalTotal = \App\Models\MasukGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                ->where('id_barang_setengah_jadi_hp', $state)
                                ->sum('isi');

                            $hasilTotalQuery = \App\Models\HasilGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                ->where('id_barang_setengah_jadi_hp', $state);
                            
                            if ($record) {
                                $hasilTotalQuery->where('id', '!=', $record->id);
                            }

                            $hasilTotal = $hasilTotalQuery->sum('isi');

                            $sisa = $modalTotal - $hasilTotal;
                            if ($sisa > 0) {
                                $set('isi', max(0, $sisa));
                            }
                        }
                    })
                    ->options(function (callable $get, $livewire, ?\App\Models\HasilGrajiTriplek $record) {

                        $query = BarangSetengahJadiHp::query()
                            ->with([
                                'ukuran',
                                'jenisBarang',
                                'grade.kategoriBarang',
                            ])
                            // ✅ WAJIB PLYWOOD
                            ->whereHas('grade.kategoriBarang', function ($q) {
                                $q->where('nama_kategori', 'PLYWOOD');
                            })
                            ->joinRelationship('jenisBarang')
                            ->joinRelationship('ukuran');

                        $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                        
                        $sisaPerBarang = [];
                        $hasNullModal = false;

                        if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiGrajitriplek) {
                            $modalBarang = \App\Models\MasukGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                ->selectRaw('id_barang_setengah_jadi_hp, sum(isi) as total_modal')
                                ->groupBy('id_barang_setengah_jadi_hp')
                                ->get();
                                
                            foreach($modalBarang as $mb) {
                                if ($mb->id_barang_setengah_jadi_hp === null) {
                                    $hasNullModal = true;
                                }
                            }

                            $hasilBarangQuery = \App\Models\HasilGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                ->whereNotNull('id_barang_setengah_jadi_hp');
                            
                            if ($record) {
                                $hasilBarangQuery->where('id', '!=', $record->id);
                            }
                            
                            $hasilBarang = $hasilBarangQuery
                                ->selectRaw('id_barang_setengah_jadi_hp, sum(isi) as total_hasil')
                                ->groupBy('id_barang_setengah_jadi_hp')
                                ->pluck('total_hasil', 'id_barang_setengah_jadi_hp');

                            $availableIds = [];
                            foreach ($modalBarang as $mb) {
                                if ($mb->id_barang_setengah_jadi_hp !== null) {
                                    $totalHasil = $hasilBarang[$mb->id_barang_setengah_jadi_hp] ?? 0;
                                    $sisa = $mb->total_modal - $totalHasil;
                                    if ($sisa > 0) {
                                        $availableIds[] = $mb->id_barang_setengah_jadi_hp;
                                        $sisaPerBarang[$mb->id_barang_setengah_jadi_hp] = $sisa;
                                    }
                                }
                            }
                            
                            if ($record && $record->id_barang_setengah_jadi_hp && !in_array($record->id_barang_setengah_jadi_hp, $availableIds)) {
                                $availableIds[] = $record->id_barang_setengah_jadi_hp;
                                $totalModalEdit = \App\Models\MasukGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi_hp', $record->id_barang_setengah_jadi_hp)
                                    ->sum('isi');
                                $totalHasilEdit = $hasilBarang[$record->id_barang_setengah_jadi_hp] ?? 0;
                                $sisaPerBarang[$record->id_barang_setengah_jadi_hp] = max(0, $totalModalEdit - $totalHasilEdit);
                            }

                            // Jika ada modal dari Gudang Triplek Mentah, jangan batasi pilihannya
                            // karena Gudang Triplek Mentah tidak punya id_barang_setengah_jadi_hp
                            if (!$hasNullModal) {
                                $query->whereIn('barang_setengah_jadi_hp.id', $availableIds);
                            }
                        }

                        $query
                            ->orderBy('ukurans.tebal', 'asc')
                            ->orderBy('barang_setengah_jadi_hp.id', 'asc');

                        return $query->get()->mapWithKeys(function ($b) use ($sisaPerBarang) {
                            $kategori = $b->grade?->kategoriBarang?->nama_kategori ?? '-';
                            $ukuran   = $b->ukuran?->nama_ukuran ?? '-';
                            $grade    = $b->grade?->nama_grade ?? '-';
                            $jenis    = $b->jenisBarang?->nama_jenis_barang ?? '-';
                            
                            $sisaInfo = isset($sisaPerBarang[$b->id]) ? " · {$sisaPerBarang[$b->id]} lbr" : '';

                            return [
                                $b->id => "{$kategori} | {$ukuran} | {$grade} | {$jenis}{$sisaInfo}"
                            ];
                        });
                    })
                    ->columnSpanFull(),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required(),

                TextInput::make('isi')
                    ->label('Isi')
                    ->numeric()
                    ->required()
                    ->rules([
                        fn (callable $get, $livewire, ?\App\Models\HasilGrajiTriplek $record) => function (string $attribute, $value, \Closure $fail) use ($get, $livewire, $record) {
                            $idBarang = $get('id_barang_setengah_jadi_hp');
                            if (!$idBarang) return;

                            $ownerRecord = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : null;
                            if ($ownerRecord && $ownerRecord instanceof \App\Models\ProduksiGrajitriplek) {
                                // Cek apakah ada modal dari Gudang Triplek Mentah
                                $hasNullModal = \App\Models\MasukGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                    ->whereNull('id_barang_setengah_jadi_hp')
                                    ->exists();
                                    
                                // Jika ada, maka validasi kuantitas tidak diketatkan (bebas)
                                if ($hasNullModal) return;

                                $modalTotal = \App\Models\MasukGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi_hp', $idBarang)
                                    ->sum('isi');

                                $hasilTotalQuery = \App\Models\HasilGrajiTriplek::where('id_produksi_graji_triplek', $ownerRecord->id)
                                    ->where('id_barang_setengah_jadi_hp', $idBarang);
                                
                                if ($record) {
                                    $hasilTotalQuery->where('id', '!=', $record->id);
                                }

                                $hasilTotal = $hasilTotalQuery->sum('isi');
                                $sisa = $modalTotal - $hasilTotal;

                                if ($value > $sisa) {
                                    $fail("Jumlah isi melebihi sisa yang tersedia dari Modal ({$sisa}).");
                                }
                            }
                        },
                    ]),

                
            ]);
    }
}
