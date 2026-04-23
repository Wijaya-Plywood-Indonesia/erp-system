<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\DetailHasilPaletRotary;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRotary;
use App\Models\ProduksiStik;
use App\Services\Akuntansi\RotaryJurnalService;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class SerahTerimaRelationManager extends RelationManager
{
    protected static string $relationship = 'serahTerima';

    protected function getTipePenerima(): string
    {
        return match (get_class($this->getOwnerRecord())) {
            ProduksiRotary::class     => 'rotary',
            ProduksiPressDryer::class => 'dryer',
            ProduksiStik::class       => 'stik',
            default                   => 'unknown',
        };
    }

    protected function getStatusByTipe(string $tipe): string
    {
        return match ($tipe) {
            'rotary' => 'Serah Barang',
            default  => 'Terima Barang',
        };
    }

    protected function getLabelByTipe(string $tipe): string
    {
        return match ($tipe) {
            'rotary' => 'Diserahkan Oleh',
            default  => 'Diterima Oleh',
        };
    }

    public function form(Schema $schema): Schema

    {
        return $schema
            ->schema([
                Select::make('id_detail_hasil_palet_rotary')
                    ->label('Nomor Palet')
                    ->options(function () {
                        $tipe = $this->getTipePenerima();
                        $idProduksi = $this->getOwnerRecord()->id;

                        $sudahSerah = DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('tipe', 'rotary')
                            ->pluck('id_detail_hasil_palet_rotary')
                            ->toArray();

                        if ($tipe === 'rotary') {
                            return DetailHasilPaletRotary::where('id_produksi', $idProduksi)
                                ->whereNotIn('id', $sudahSerah)
                                ->get()
                                ->mapWithKeys(fn($d) => [
                                    $d->id => "{$d->kode_palet} - {$d->total_lembar} lembar"
                                ]);
                        }
                        return [];
                    })
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('diserahkan_oleh')
                    ->label(fn() => $this->getLabelByTipe($this->getTipePenerima()))
                    ->default(fn() => Auth::user()->name)
                    ->readOnly()
                    ->columnSpanFull(),

                // Hidden field untuk menyimpan tipe default saat create
                \Filament\Forms\Components\Hidden::make('tipe')
                    ->default(fn() => $this->getTipePenerima()),

                \Filament\Forms\Components\Hidden::make('status')
                    ->default(fn() => $this->getStatusByTipe($this->getTipePenerima())),
            ]);
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipePenerima();

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe) {
                $query->with([
                    'detailHasilPalet.ukuran',
                    'detailHasilPalet.penggunaanLahan.jenisKayu',
                ]);

                if ($tipe === 'rotary') {
                    return $query->where('tipe', 'rotary');
                }

                $ownerId = $this->getOwnerRecord()->id;

                // Reset wheres untuk view Penerima agar bisa melihat barang 'Ready' dari Rotary
                $query->getQuery()->wheres = [];
                $query->getQuery()->bindings['where'] = [];

                return $query->where(function ($mainQuery) use ($tipe, $ownerId) {
                    $mainQuery->where(function ($q) {
                        $q->where('tipe', 'rotary')->where('diterima_oleh', '-');
                    })
                        ->orWhere(function ($q) use ($tipe, $ownerId) {
                            $q->where('tipe', $tipe)->where('id_produksi', $ownerId);
                        });
                })
                // REVISI: Urutkan agar yang belum diterima ('-') berada di paling atas
                ->orderBy('diterima_oleh', 'asc')
                ->orderBy('created_at', 'desc');
            })
            ->columns([
                TextColumn::make('detailHasilPalet.palet')
                    ->label('Nomor Palet')
                    ->getStateUsing(fn($record) => $record->detailHasilPalet?->kode_palet ?? '-')
                    ->searchable(query: function ($query, string $search) {

                        $kodeMapping = [
                            'SP' => 'SPINDLESS',
                            'MR' => 'MERANTI',
                            'SJ' => 'SANJI',
                            'YQ' => 'YUEQUN',
                        ];

                        $parts      = explode('-', strtoupper(trim($search)));
                        $kodeInput  = $parts[0] ?? null;
                        $nomorPalet = isset($parts[1]) && is_numeric($parts[1]) ? (int) $parts[1] : null;
                        $namaMesin  = $kodeMapping[$kodeInput] ?? null;

                        $query->whereHas('detailHasilPalet', function ($q) use ($search, $namaMesin, $nomorPalet) {
                            // Join ke produksi dan mesin untuk bisa filter nama mesin
                            $q->join('produksi_rotaries', 'detail_hasil_palet_rotaries.id_produksi', '=', 'produksi_rotaries.id')
                                ->join('mesins', 'produksi_rotaries.id_mesin', '=', 'mesins.id');

                            if ($namaMesin && $nomorPalet !== null) {
                                // Input: "SP-1"
                                $q->where('mesins.nama_mesin', 'like', "%{$namaMesin}%")
                                    ->where('detail_hasil_palet_rotaries.palet', $nomorPalet);
                            } elseif ($namaMesin) {
                                // Input: "SP"
                                $q->where('mesins.nama_mesin', 'like', "%{$namaMesin}%");
                            } elseif (is_numeric($search)) {
                                // Input: "25" (nomor palet saja)
                                $q->where('detail_hasil_palet_rotaries.palet', (int) $search);
                            } else {
                                // Input: "SPINDLESS" (nama mesin langsung)
                                $q->where('mesins.nama_mesin', 'like', "%{$search}%");
                            }
                        });
                    }),

                TextColumn::make('detailHasilPalet.total_lembar')
                    ->label('Lembar')
                    ->numeric(),

                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailHasilPalet?->ukuran
                            ? "{$record->detailHasilPalet->ukuran->panjang} x {$record->detailHasilPalet->ukuran->lebar} x {$record->detailHasilPalet->ukuran->tebal}"
                            : '-'
                    ),

                TextColumn::make('detailHasilPalet.kw')
                    ->label('KW')
                    ->alignCenter(),

                TextColumn::make('diserahkan_oleh')
                    ->label('Oleh')
                    ->badge(),

                TextColumn::make('diterima_oleh')
                    ->badge()
                    ->color(fn($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn($state) => $state === '-' ? 'Menunggu' : $state),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Terima Barang' => 'success',
                        'Serah Barang' => 'warning',
                        default => 'gray'
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Serahkan Palet')
                    ->visible(fn() => $this->getTipePenerima() === 'rotary')
                    // Logic stok TIDAK ADA di sini agar tidak otomatis masuk saat serah
                    ->after(fn() => Notification::make()->title('Palet Berhasil Diserahkan')->info()->send()),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $tipe !== 'rotary' && $record?->diterima_oleh === '-')
                    ->action(function ($record) use ($tipe) {
                        DB::transaction(function () use ($record, $tipe) {
                            $unitAsal = 'ROTARY';
                            $unitTujuan = match ($tipe) {
                                'dryer' => 'DRYER',
                                'stik'  => 'STIK',
                                default => 'GUDANG',
                            };

                            $userSerah = $record->diserahkan_oleh;
                            $userTerima = Auth::user()->name;
                            $diterimaOleh = "{$userTerima} - Produksi {$unitTujuan}";

                            // Update Sisi Rotary
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id', $record->id)
                                ->update(['diterima_oleh' => $diterimaOleh, 'status' => 'Terima Barang', 'updated_at' => now()]);

                            // Insert Sisi Penerima
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->insert([
                                'id_detail_hasil_palet_rotary' => $record->id_detail_hasil_palet_rotary,
                                'diserahkan_oleh'              => $userSerah,
                                'diterima_oleh'                => $diterimaOleh,
                                'tipe'                         => $tipe,
                                'id_produksi'                  => $this->getOwnerRecord()->id,
                                'status'                       => 'Terima Barang',
                                'created_at'                   => now(), 'updated_at' => now(),
                            ]);

                            // Eksekusi Stok
                            $palet = $record->detailHasilPalet;
                            if ($palet) {
                                $service = app(RotaryJurnalService::class);
                                // FORMAT KETERANGAN BERSIH
                                $keteranganFinal = "SERAH-TERIMA: {$palet->kode_palet} | {$unitAsal} -> {$unitTujuan} | Oleh: {$userSerah} -> {$userTerima}";
                                $service->serahPalet($palet, $keteranganFinal);
                            }
                        });
                        Notification::make()->title('Palet Berhasil Diterima')->success()->send();
                    }),
            ]);
    }
}
