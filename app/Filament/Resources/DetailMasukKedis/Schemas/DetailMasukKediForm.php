<?php

namespace App\Filament\Resources\DetailMasukKedis\Schemas;

use App\Models\JenisKayu;
use App\Models\SerahTerimaVeneerBasah;
use App\Models\Ukuran;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class DetailMasukKediForm
{
    public static function configure(Schema $schema, ?int $idProduksiKedi = null): Schema
    {
        return $schema
            ->components([

                Select::make('id_serah_terima_veneer_basah')
                    ->label('Veneer Basah Diterima (dari Gudang)')
                    ->helperText('Hanya menampilkan veneer basah yang sudah dikonfirmasi "Terima" dari Gudang dan masih ada sisa yang belum dipakai.')
                    ->options(function ($record) {
                        $sudahDipakai = DB::table('detail_masuk_kedi')
                            ->whereNotNull('id_serah_terima_veneer_basah')
                            ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                            ->selectRaw('id_serah_terima_veneer_basah, SUM(jumlah) as total_dipakai')
                            ->groupBy('id_serah_terima_veneer_basah')
                            ->pluck('total_dipakai', 'id_serah_terima_veneer_basah');

                        $rows = SerahTerimaVeneerBasah::with(['detail.ukuran', 'detail.jenisKayu'])
                            ->where('tujuan', 'kedi')
                            ->where('status', 'Diterima')
                            ->get();

                        $options = [];
                        foreach ($rows as $row) {
                            $d = $row->detail;
                            if (! $d) {
                                continue;
                            }
                            $dipakai = (int) ($sudahDipakai[$row->id] ?? 0);
                            $sisa = (int) $d->qty_lembar - $dipakai;
                            if ($sisa <= 0) {
                                continue;
                            }
                            $ukuran = $d->ukuran ? "{$d->ukuran->panjang}x{$d->ukuran->lebar}x{$d->ukuran->tebal}" : '-';
                            $kayu = $d->jenisKayu?->nama_kayu ?? '-';
                            $options[$row->id] = "{$kayu} | {$ukuran} | KW {$d->kw} | sisa {$sisa} lbr";
                        }

                        return $options;
                    })
                    ->searchable()
                    ->required(fn ($record) => $record === null)
                    ->live()
                    ->disabled(fn ($record) => $record !== null)
                    ->afterStateUpdated(function ($set, ?string $state) {
                        if (! $state) {
                            return;
                        }

                        $row = SerahTerimaVeneerBasah::with(['detail.ukuran', 'detail.jenisKayu'])->find($state);
                        if (! $row || ! $row->detail) {
                            return;
                        }

                        $dipakai = (int) DB::table('detail_masuk_kedi')
                            ->where('id_serah_terima_veneer_basah', $state)
                            ->sum('jumlah');
                        $sisa = (int) $row->detail->qty_lembar - $dipakai;

                        $set('id_jenis_kayu', $row->detail->id_jenis_kayu);
                        $set('id_ukuran', $row->detail->id_ukuran);
                        $set('kw', $row->detail->kw);
                        $set('jumlah', $sisa);
                        $set('sisa_tersedia', $sisa);
                        Session::put('last_jenis_kayu', $row->detail->id_jenis_kayu);
                    })
                    ->columnSpanFull(),

                Hidden::make('sisa_tersedia')->dehydrated(false),

                Select::make('id_jenis_kayu')
                    ->label('Jenis Kayu')
                    ->options(JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id'))
                    ->searchable()
                    ->disabled()
                    ->dehydrated(true)
                    ->required(),

                Select::make('id_ukuran')
                    ->label('Ukuran')
                    ->options(
                        Ukuran::all()
                            ->sortBy(fn ($u) => $u->dimensi)
                            ->mapWithKeys(fn ($u) => [$u->id => $u->dimensi])
                    )
                    ->searchable()
                    ->disabled()
                    ->dehydrated(true)
                    ->required(),

                TextInput::make('kw')
                    ->label('KW (Kualitas)')
                    ->required()
                    ->readOnly()
                    ->dehydrated(true),

                TextInput::make('jumlah')
                    ->label('Jumlah (Lembar)')
                    ->helperText('Boleh diisi kurang dari sisa yang tersedia — sisanya tetap bisa dipakai produksi lain nanti.')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->dehydrated(true),

                TextInput::make('no_palet')
                    ->label('Nomor Palet')
                    ->numeric()
                    ->default(function () use ($idProduksiKedi) {
                        if (! $idProduksiKedi) {
                            return 1;
                        }

                        $last = (int) DB::table('detail_masuk_kedi')
                            ->where('id_produksi_kedi', $idProduksiKedi)
                            ->max('no_palet');

                        return $last + 1;
                    })
                    ->required()
                    ->dehydrated(true),
            ]);
    }
}