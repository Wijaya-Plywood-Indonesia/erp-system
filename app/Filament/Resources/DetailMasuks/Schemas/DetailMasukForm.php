<?php

namespace App\Filament\Resources\DetailMasuks\Schemas;

use App\Models\DetailHasilPaletRotary;
use App\Models\DetailMasuk;
use App\Models\DetailMasukStik;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;

class DetailMasukForm
{
    public static function configure(
        Schema $schema,
        ?int $idProduksi = null,
        string $tipe = 'dryer'
    ): Schema {
        // 1. Tentukan Model dan Foreign Key secara dinamis
        $modelClass = $tipe === 'stik' ? DetailMasukStik::class : DetailMasuk::class;
        $foreignKey = $tipe === 'stik' ? 'id_produksi_stik' : 'id_produksi_dryer';

        return $schema->schema([

            // ✅ Simpan ID Produksi ke kolom yang benar agar tidak "Undefined id_produksi"
            Hidden::make($foreignKey)
                ->default($idProduksi)
                ->required()
                ->dehydrated(true),

            Select::make('no_palet_select')
                ->label('Nomor Palet')
                ->options(function ($record, $livewire) use ($tipe, $modelClass) {
                    // Ambil ID palet yang sudah terpakai
                    $usedPallets = DB::table($modelClass::make()->getTable())
                        ->whereNotNull('no_palet')
                        ->where('no_palet', '>', 0)
                        ->pluck('no_palet')
                        ->map(fn($id) => (int) $id)
                        ->toArray();

                    $idDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                        ->where('tipe', $tipe)
                        ->whereNotNull('id_detail_hasil_palet_rotary')
                        ->pluck('id_detail_hasil_palet_rotary')
                        ->toArray();

                    $palets = DetailHasilPaletRotary::whereIn('id', $idDiterima)->get();

                    $options = [];
                    foreach ($palets as $p) {
                        $isUsed = in_array($p->id, $usedPallets);

                        if ($record && (int)$record->getRawOriginal('no_palet') === $p->id) {
                            $isUsed = false;
                        }

                        if (!$isUsed) {
                            $options[$p->id] = $p->kode_palet ?? $p->palet;
                        }
                    }

                    return ['AF' => 'Palet AF'] + $options;
                })
                ->searchable()
                ->required()
                ->live()
                ->disabled(fn($record) => $record !== null)
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, ?string $state, $livewire) use ($tipe, $modelClass) {
                    // ✅ PERBAIKAN: Handle AF dengan generate ID negatif
                    if ($state === 'AF') {
                        // Generate nomor AF baru (menggunakan nilai negatif)
                        $lastAF = DB::table($modelClass::make()->getTable())
                            ->where('no_palet', '<', 0)
                            ->min('no_palet'); // Cari nilai negatif terkecil

                        // Generate ID baru: -1, -2, -3, ...
                        $newAFId = $lastAF ? $lastAF - 1 : -1;

                        $set('no_palet', $newAFId);
                        $set('af_generated_id', $newAFId);

                        // Reset fields untuk input manual
                        $set('id_jenis_kayu', null);
                        $set('id_ukuran', null);
                        $set('kw', null);
                        $set('isi', null);

                        return;
                    }

                    // Handle palet biasa
                    if ($state && $state !== 'AF') {
                        $set('no_palet', (int) $state);

                        $palet = DetailHasilPaletRotary::with(['penggunaanLahan', 'ukuran'])->find($state);
                        if ($palet) {
                            $set('kw', $palet->kw);
                            $set('isi', $palet->total_lembar);
                            $set('id_jenis_kayu', $palet->penggunaanLahan?->id_jenis_kayu);
                            $set('id_ukuran', $palet->id_ukuran);
                        }
                    }
                })
                ->columnSpanFull(),

            // =========================================================
            // PERBAIKAN: Hidden field untuk no_palet (hanya 1)
            // =========================================================
            Hidden::make('no_palet')
                ->required()
                ->dehydrated(true),

            // =========================================================
            // PERBAIKAN: Placeholder untuk AF Preview
            // =========================================================
            Placeholder::make('af_preview')
                ->label('Nomor AF yang akan digunakan')
                ->content(function (Get $get) {
                    $noPalet = $get('no_palet');
                    if ($noPalet !== null && (int) $noPalet < 0) {
                        return 'AF-' . abs((int) $noPalet);
                    }
                    return '-';
                })
                ->visible(fn(Get $get) => $get('no_palet_select') === 'AF')
                ->columnSpanFull(),

            Select::make('id_jenis_kayu')
                ->label('Jenis Kayu')
                ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                ->searchable()
                ->disabled(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true)
                ->required(),

            Select::make('id_ukuran')
                ->label('Ukuran')
                ->options(Ukuran::all()->pluck('nama_ukuran', 'id'))
                ->searchable()
                ->disabled(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true)
                ->required(),

            TextInput::make('kw')
                ->label('KW (Kualitas)')
                ->required()
                ->readOnly(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true),

            TextInput::make('isi')
                ->label('Isi')
                ->required()
                ->numeric()
                ->readOnly(fn(Get $get) => $get('no_palet_select') !== 'AF' && $get('no_palet_select') !== null)
                ->dehydrated(true),
        ]);
    }
}
