<?php

namespace App\Filament\Resources\BahanHotPresses\Schemas;

use App\Models\BahanHotpress;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use App\Models\BarangSetengahJadiHp;
use App\Models\JenisBarang;
use App\Models\Grade;
use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Schemas\Components\Utilities\Get;

class BahanHotPressForm
{
    public static function configure(Schema $schema): Schema
    {
        $getLastRencana = fn($livewire) =>
        $livewire->ownerRecord
            ?->rencanaKerjaHp()
            ->latest()
            ->with('barangSetengahJadiHp')
            ->first();

        return $schema
            ->columns(2)
            ->components([
                Select::make('grade_id')
                    ->label('Filter Grade')
                    ->options(
                        Grade::with('kategoriBarang')
                            ->orderBy('id_kategori_barang')
                            ->orderBy('nama_grade')
                            ->get()
                            ->mapWithKeys(fn($g) => [
                                $g->id => ($g->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    . ' | ' . $g->nama_grade
                            ])
                    )
                    ->reactive()
                    ->searchable()
                    ->placeholder('Semua Grade')
                    ->dehydrated(false),

                // =========================================================================
                // 2. FILTER JENIS BARANG (OPSIONAL) - TIDAK DEHYDRATED
                // =========================================================================
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

                // =========================================================================
                // 3. BARANG SETENGAH JADI (SELECT UTAMA) - DEHYDRATED (Disimpan)
                // =========================================================================
                Select::make('id_mutasi_keluar_palet')
                    ->label('Barang Setengah Jadi')
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->options(fn(?BahanHotpress $record) => self::getPaletOptions($record))
                    ->afterStateUpdated(function ($state, callable $set, ?BahanHotpress $record) {
                        if (! $state) {
                            $set('isi', null);
                            $set('no_palet', null);
                            $set('sisa_tersedia', null);
                            return;
                        }

                        $palet = VeneerJadiMutasiKeluarPalet::find($state);
                        if (! $palet) return;

                        $sisa = $palet->sisa;
                        if ($record && $record->id_mutasi_keluar_palet === (int) $state) {
                            $sisa += (float) $record->isi;
                        }

                        $set('no_palet', $palet->nomor_palet);
                        $set('sisa_tersedia', $sisa);
                        $set('isi', $sisa);
                    })
                    ->columnSpanFull(),

                TextInput::make('sisa_tersedia')
                    ->label('Sisa Tersedia (Lembar)')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($set, ?BahanHotPress $record) {
                        if (! $record?->id_barang_setengah_jadi) {
                            return;
                        }

                        $palet = VeneerJadiMutasiKeluarPalet::find($record->id_barang_setengah_jadi);

                        if (! $palet) {
                            return;
                        }

                        $set('sisa_tersedia', $palet->sisa + (float) $record->isi);
                    }),

                TextInput::make('isi')
                    ->label('Isi (Digunakan)')
                    ->numeric()
                    ->required()
                    ->live()
                    ->rules([
                        fn(Get $get, ?BahanHotpress $record) => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                            $idPalet = $get('id_barang_setengah_jadi');

                            if (! $idPalet) {
                                return;
                            }

                            $palet = VeneerJadiMutasiKeluarPalet::find($idPalet);

                            if (! $palet) {
                                return;
                            }

                            $sisa = $palet->sisa;
                            if ($record && $record->id_barang_setengah_jadi === (int) $idPalet) {
                                $sisa += (float) $record->isi;
                            }

                            if ($value > $sisa) {
                                $fail("Isi melebihi sisa yang tersedia ({$sisa} lembar).");
                            }
                        },
                    ]),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->required()
                    ->live(),
            ]);
    }

    protected static function getPaletOptions(?BahanHotPress $record): array
    {
        $currentId = $record?->id_barang_setengah_jadi;
        $currentIsi = (float) ($record?->isi ?? 0);

        return VeneerJadiMutasiKeluarPalet::query()
            ->whereHas('mutasiKeluar', fn($q) => $q->whereNotNull('diterima_by'))
            ->with('mutasiKeluar.jenisKayu')
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($palet) use ($currentId, $currentIsi) {
                $sisa = $palet->sisa + ($palet->id === $currentId ? $currentIsi : 0);
                return [$palet, $sisa];
            })
            ->filter(fn($pair) => $pair[1] > 0)
            ->mapWithKeys(function ($pair) {
                [$palet, $sisa] = $pair;
                $mk = $palet->mutasiKeluar;

                $panjang = (float) $mk->panjang + 0;
                $lebar   = (float) $mk->lebar + 0;
                $tebal   = (float) $mk->tebal + 0;
                $kw      = $mk->kw_grade ?? '?';
                $kayu    = $mk->jenisKayu?->nama_kayu ?? '?';
                $noPalet = $palet->nomor_palet ?? '?';

                $label = "Veneer | {$panjang}mm x {$lebar}mm x {$tebal}mm | {$kw} | {$kayu} "
                    . "| Palet {$noPalet} | Sisa {$sisa} Lbr";

                return [$palet->id => $label];
            })
            ->toArray();
    }
}
