<?php

namespace App\Filament\Resources\ProduksiRotaries\RelationManagers;

use App\Models\DetailHasilPaletRotary;
use App\Models\ProduksiPressDryer;
use App\Models\ProduksiRotary;
use App\Models\ProduksiStik;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

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
            ->components([
                Select::make('id_detail_hasil_palet_rotary')
                    ->label('Nomor Palet')
                    ->options(function () {
                        $tipe       = $this->getTipePenerima();
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
                                    $d->id => "{$d->palet} - {$d->total_lembar} lembar"
                                ]);
                        }

                        return [];
                    })
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('diserahkan_oleh')
                    ->label(fn() => $this->getLabelByTipe($this->getTipePenerima()))
                    ->readOnly()
                    ->content(fn() => Auth::user()->name)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $tipe = $this->getTipePenerima();

        return $table
            ->modifyQueryUsing(function ($query) use ($tipe) {

                // 🔥 EAGER LOADING (BIAR CEPAT)
                $query->with([
                    'detailHasilPalet.ukuran',
                    'detailHasilPalet.penggunaanLahan.jenisKayu',
                ]);

                if ($tipe === 'rotary') {
                    return $query->where('tipe', 'rotary');
                }

                $query->getQuery()->wheres   = [];
                $query->getQuery()->bindings = [
                    'select'     => [],
                    'from'       => [],
                    'join'       => [],
                    'where'      => [],
                    'groupBy'    => [],
                    'having'     => [],
                    'order'      => [],
                    'union'      => [],
                    'unionOrder' => [],
                ];

                $query
                    ->from('detail_hasil_palet_rotary_serah_terima_pivot')
                    ->select('detail_hasil_palet_rotary_serah_terima_pivot.*')
                    ->where(function ($q) {
                        $q->where('tipe', 'rotary')
                            ->where('diterima_oleh', '-');
                    })
                    ->orWhere(function ($q) use ($tipe) {
                        $q->where('tipe', $tipe);
                    });

                return $query;
            })
            ->columns([

                // PALLET
                TextColumn::make('detailHasilPalet.palet')
                    ->label('Nomor Palet')
                    ->getStateUsing(fn($record) => $record->detailHasilPalet?->kode_palet ?? '-')
                    ->searchable(),

                // JUMLAH
                TextColumn::make('detailHasilPalet.total_lembar')
                    ->label('Jumlah (Lembar)')
                    ->numeric(),

                // 🔥 UKURAN
                TextColumn::make('ukuran')
                    ->label('Ukuran')
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailHasilPalet?->ukuran
                            ? "{$record->detailHasilPalet->ukuran->panjang} x {$record->detailHasilPalet->ukuran->lebar} x {$record->detailHasilPalet->ukuran->tebal}"
                            : '-'
                    ),

                // 🔥 KW
                TextColumn::make('detailHasilPalet.kw')
                    ->label('KW')
                    ->alignCenter(),

                // 🔥 JENIS KAYU
                TextColumn::make('jenis_kayu')
                    ->label('Jenis Kayu')
                    ->getStateUsing(
                        fn($record) =>
                        $record->detailHasilPalet?->penggunaanLahan?->jenisKayu?->nama_kayu ?? '-'
                    ),

                // DISERAHKAN
                TextColumn::make('diserahkan_oleh')
                    ->badge(),

                // DITERIMA
                TextColumn::make('diterima_oleh')
                    ->badge()
                    ->color(fn($state) => $state === '-' ? 'gray' : 'success')
                    ->formatStateUsing(fn($state) => $state === '-' ? 'Belum Diterima' : $state),

                // STATUS
                TextColumn::make('status')
                    ->badge(),

                // WAKTU
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Serahkan Palet')
                    ->visible(fn() => $this->getTipePenerima() === 'rotary'),
            ])
            ->actions([
                Action::make('terima')
                    ->label('Terima')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn($record) => $tipe !== 'rotary' && $record?->diterima_oleh === '-')
                    ->action(function ($record) use ($tipe) {

                        DB::transaction(function () use ($record, $tipe) {

                            $labelProduksi = match ($tipe) {
                                'dryer' => 'Produksi Dryer',
                                'stik'  => 'Produksi Stik',
                                default => 'Produksi',
                            };

                            $diterimaOleh = Auth::user()->name . ' - ' . $labelProduksi;

                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id', $record->id)
                                ->update([
                                    'diterima_oleh' => $diterimaOleh,
                                    'status'        => 'Terima Barang',
                                    'updated_at'    => now(),
                                ]);

                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->insert([
                                    'id_detail_hasil_palet_rotary' => $record->id_detail_hasil_palet_rotary,
                                    'diserahkan_oleh'              => $record->diserahkan_oleh,
                                    'diterima_oleh'                => $diterimaOleh,
                                    'tipe'                         => $tipe,
                                    'status'                       => 'Terima Barang',
                                    'created_at'                   => now(),
                                    'updated_at'                   => now(),
                                ]);
                        });

                        Notification::make()
                            ->title('Palet berhasil diterima')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }
}
