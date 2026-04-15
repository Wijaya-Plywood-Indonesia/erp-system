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

            // ✅ Simpan ID Palet asli ke kolom 'no_palet'
            Hidden::make('no_palet')
                ->required()
                ->dehydrated(true),

            Select::make('no_palet_select')
                ->label('Nomor Palet')
                ->options(function ($record, $livewire) use ($tipe, $modelClass) {
                    // 1. Ambil ID palet yang sudah terpakai secara GLOBAL
                    $usedPallets = DB::table($modelClass::make()->getTable())
                        ->whereNotNull('no_palet')
                        ->where('no_palet', '>', 0)
                        ->pluck('no_palet')
                        ->map(fn($id) => (int) $id)
                        ->toArray();

                    // 2. Ambil ID palet yang sudah diterima dari Rotary (Pivot)
                    $idDiterima = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                        ->where('tipe', $tipe)
                        ->whereNotNull('id_detail_hasil_palet_rotary')
                        ->pluck('id_detail_hasil_palet_rotary')
                        ->toArray();

                    // 3. Ambil data palet asli
                    $palets = DetailHasilPaletRotary::whereIn('id', $idDiterima)->get();

                    // 4. Proses Filter berdasarkan "Status Bayangan"
                    $options = [];
                    foreach ($palets as $p) {
                        $isUsed = in_array($p->id, $usedPallets);

                        // Logika Edit: Jika ini palet milik data ini sendiri, anggap TIDAK terpakai agar tetap muncul
                        if ($record && (int)$record->getRawOriginal('no_palet') === $p->id) {
                            $isUsed = false;
                        }

                        // JIKA STATUSNYA "TERSEDIA" (TIDAK DIGUNAKAN), BARU MASUKKAN KE DAFTAR
                        if (!$isUsed) {
                            $options[$p->id] = $p->kode_palet ?? $p->palet;
                        }
                    }

                    return ['AF' => '— Palet AF (Manual) —'] + $options;
                })
                ->searchable()
                ->required()
                ->live()
                ->disabled(fn($record) => $record !== null)
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if (!$state || $state === 'AF') return;

                    $set('no_palet', (int) $state);

                    $palet = DetailHasilPaletRotary::with(['penggunaanLahan', 'ukuran'])->find($state);
                    if ($palet) {
                        $set('kw', $palet->kw);
                        $set('isi', $palet->total_lembar);
                        $set('id_jenis_kayu', $palet->penggunaanLahan?->id_jenis_kayu);
                        $set('id_ukuran', $palet->id_ukuran);
                    }
                })
                ->columnSpanFull(),

            // Pastikan Hidden field ini tetap ada untuk menyimpan ID ke DB
            Hidden::make('no_palet')->required()->dehydrated(true),

            Placeholder::make('af_preview')
                ->label('Nomor AF yang akan digunakan')
                ->content(function (Get $get) {
                    $noPalet = $get('no_palet');
                    if ($noPalet !== null && (int) $noPalet < 0) {
                        return 'AF-' . abs((int) $noPalet);
                    }
                    return '-';
                })
                ->hidden(fn(Get $get) => $get('no_palet_select') !== 'AF')
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
