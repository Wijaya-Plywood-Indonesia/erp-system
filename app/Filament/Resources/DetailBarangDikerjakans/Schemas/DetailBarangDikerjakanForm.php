<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\PegawaiNyusup;

class DetailBarangDikerjakanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('id_pegawai_nyusup')
                    ->label('Pegawai')
                    ->required()
                    ->searchable()
                    ->options(function ($livewire) {

                        // 🔑 Ambil Produksi Nyusup (parent)
                        $produksi = $livewire->getOwnerRecord();

                        if (!$produksi) {
                            return [];
                        }

                        return PegawaiNyusup::with('pegawai')
                            ->where('id_produksi_nyusup', $produksi->id)
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => $p->pegawai->nama_pegawai
                            ]);
                    })
                    ->columnSpanFull(),

                \Filament\Forms\Components\Hidden::make('id_barang_setengah_jadi_hp'),

                \App\Support\PaletSearch::configure(
                    Select::make('id_serah_terima_gudang_satu')
                        ->label('Pilih Palet Modal (Dari Serah Terima)'),
                    function (callable $get, ?\App\Models\DetailBarangDikerjakan $record) {
                        return self::getPaletEntries($record);
                    },
                )
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if (!$state) {
                            $set('id_barang_setengah_jadi_hp', null);
                            $set('modal', null);
                            $set('hasil', null);
                            return;
                        }

                        $serahTerima = \App\Models\SerahTerimaGudangSatu::find($state);
                        if ($serahTerima) {
                            $set('id_barang_setengah_jadi_hp', $serahTerima->barangSetengahJadi?->id);
                            
                            $noPalet = $serahTerima->hasilNyusup?->no_palet;
                            
                            if ($noPalet) {
                                $set('no_palet', $noPalet);
                            }
                            
                            $sisa = $serahTerima->sisa;
                            if ($sisa > 0) {
                                $set('modal', $sisa);
                                $set('hasil', $sisa);
                            }
                        }
                    })
                    ->columnSpanFull(),

                TextInput::make('modal')
                    ->label('Modal Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->rules([
                        fn (callable $get, ?\App\Models\DetailBarangDikerjakan $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idSerahTerima = $get('id_serah_terima_gudang_satu');
                            if (!$idSerahTerima) return;

                            $serahTerima = \App\Models\SerahTerimaGudangSatu::find($idSerahTerima);
                            if (!$serahTerima) return;

                            $sisa = $serahTerima->sisa;

                            if ($record && $record->id_serah_terima_gudang_satu === (int) $idSerahTerima) {
                                $sisa += (float) $record->modal;
                            }

                            if ($value > $sisa) {
                                $fail("Jumlah modal melebihi sisa yang tersedia dari palet ({$sisa} lbr).");
                            }
                        },
                    ]),

                TextInput::make('hasil')
                    ->label('Hasil Nyusup')
                    ->numeric()
                    ->minValue(1)
                    ->required(),

                TextInput::make('no_palet')
                    ->label('No Palet')
                    ->numeric()
                    ->required(),
            ]);
    }

    /**
     * Data palet untuk dropdown "Pilih Palet Modal" + bahan pencarian fleksibel
     * (App\Support\PaletSearch).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function getPaletEntries(?\App\Models\DetailBarangDikerjakan $record): array
    {
        $currentId = $record?->id_serah_terima_gudang_satu;
        $currentModal = (float) ($record?->modal ?? 0);

        return \App\Models\SerahTerimaGudangSatu::query()
            ->where('diterima_oleh', '!=', '-')
            ->where('tujuan', 'nyusup')
            ->with([
                'hasilPilihPlywood.barangSetengahJadiHp',
                'hasilTerimaGudangSatu',
                'hasilNyusup',
                'triplekMutasiKeluar'
            ])
            ->get()
            ->map(function ($item) use ($currentId, $currentModal) {
                $sisa = $item->sisa + ($item->id === $currentId ? $currentModal : 0);
                return [$item, $sisa];
            })
            ->filter(fn ($pair) => $pair[1] > 0)
            ->map(function ($pair) {
                [$item, $sisa] = $pair;
                $sisaLabel = rtrim(rtrim(number_format($sisa, 2, '.', ''), '0'), '.');

                $b = $item->barangSetengahJadi;
                $ukuran = $b?->ukuran?->nama_ukuran ?? ($b?->tebal ? $b->panjang . 'x' . $b->lebar . 'x' . $b->tebal : '-');
                $grade = $b?->grade?->nama_grade ?? ($b?->kw_grade ?? '-');
                $jenis = $b?->jenisBarang?->nama_jenis_barang ?? ($b?->jenisKayu?->nama_kayu ?? '-');
                $kategori = $b?->grade?->kategoriBarang?->nama_kategori ?? 'Plywood';

                // Ambil no_palet HANYA dari relasi sumber yang terbukti memiliki kolom no_palet
                $noPalet = $item->hasilNyusup?->no_palet;
                $paletPrefix = $noPalet ? "Palet {$noPalet} - " : "";

                $label = "{$paletPrefix}{$kategori} | {$ukuran} | {$grade} | {$jenis} — Sisa: {$sisaLabel} lbr";

                return [
                    'id' => $item->id,
                    'label' => $label,
                    'no' => (string) ($noPalet ?? ''),
                    'kategori' => $kategori,
                    'jenis' => $jenis,
                    'grade' => $grade,
                    'ukuran' => $ukuran,
                ];
            })
            ->values()
            ->all();
    }
}
