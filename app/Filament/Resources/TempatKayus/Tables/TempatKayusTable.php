<?php

namespace App\Filament\Resources\TempatKayus\Tables;

use App\Models\HppAverageSummarie;
use App\Models\Mesin;
use App\Models\ProduksiRotary;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TempatKayusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lahan.kode_lahan')
                    ->label('Kode Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('lahan.nama_lahan')
                    ->label('Nama Lahan')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('kayuMasuk.seri')
                    ->label('Seri Kayu')
                    ->formatStateUsing(fn($state) => 'Seri - ' . ($state ?? '-'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('jumlah_batang')
                    ->label('Jumlah Batang')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('kubikasi')
                    ->label('Kubikasi (m³)')
                    ->getStateUsing(fn($record) => number_format($record->kubikasi, 4)),

                TextColumn::make('panjang_kayu')
                    ->label('Panjang (cm)')
                    ->getStateUsing(function ($record) {
                        return HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->value('panjang') ?? '-';
                    })
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state == 130 => 'info',
                        $state == 260 => 'success',
                        default       => 'gray',
                    }),

                TextColumn::make('status_serah')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        return DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_lahan', $record->id_lahan)
                            ->where('tipe', 'lahan_rotary')
                            ->exists()
                            ? 'Sudah Diserahkan'
                            : 'Masih Dikerjakan';
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'Sudah Diserahkan' => 'success',
                        default            => 'warning',
                    }),

                TextColumn::make('poin')
                    ->label('Poin')
                    ->money('Rp.')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('lahan_siap')
                    ->label('Lahan Siap')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(function ($record) {
                        return !DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                            ->where('id_lahan', $record->id_lahan)
                            ->where('tipe', 'lahan_rotary')
                            ->exists();
                    })
                    ->modalHeading('Serahkan Kayu ke Produksi Rotary')
                    ->form(function ($record) {
                        $panjang = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->value('panjang');

                        $namaMesinTersedia = match (true) {
                            $panjang == 130 => ['Sanji', 'Yequen'],
                            $panjang == 260 => ['Spindless', 'Meranti'],
                            default         => [],
                        };

                        $produksiOptions = ProduksiRotary::whereHas('mesin', function ($q) use ($namaMesinTersedia) {
                            $q->whereIn('nama_mesin', $namaMesinTersedia);
                        })
                            ->with('mesin')
                            ->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "{$p->mesin?->nama_mesin} - {$p->tanggal_produksi}"
                            ])
                            ->toArray();

                        return [
                            Select::make('id_produksi')
                                ->label('Produksi Rotary')
                                ->options($produksiOptions)
                                ->searchable()
                                ->required()
                                ->helperText(
                                    empty($produksiOptions)
                                        ? 'Tidak ada produksi rotary yang sesuai dengan panjang kayu ini.'
                                        : 'Pilih produksi rotary yang akan mengerjakan kayu ini.'
                                )
                                ->disabled(empty($produksiOptions))
                                ->columnSpanFull(),

                            TextInput::make('diserahkan_oleh')
                                ->label('Diserahkan Oleh')
                                ->default(fn() => Auth::user()->name)
                                ->readOnly()
                                ->columnSpanFull(),
                        ];
                    })
                    ->modalSubmitActionLabel('Serahkan')
                    ->action(function ($record, array $data) {
                        DB::transaction(function () use ($record, $data) {
                            $idJenisKayu = $record->kayuMasuk
                                ?->detailMasukanKayu
                                ->first()
                                ?->id_jenis_kayu;

                            // 1. Insert ke pivot serah terima
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')->insert([
                                'id_detail_hasil_palet_rotary' => null,
                                'id_lahan'                     => $record->id_lahan,
                                'id_produksi'                  => $data['id_produksi'],
                                'jumlah_batang'                => $record->jumlah_batang,
                                'kubikasi'                     => $record->kubikasi,
                                'diserahkan_oleh'              => $data['diserahkan_oleh'],
                                'diterima_oleh'                => '-',
                                'tipe'                         => 'lahan_rotary',
                                'status'                       => 'Lahan Siap',
                                'created_at'                   => now(),
                                'updated_at'                   => now(),
                            ]);

                            // 2. Insert ke penggunaan_lahan_rotaries
                            \App\Models\PenggunaanLahanRotary::create([
                                'id_lahan'      => $record->id_lahan,
                                'id_produksi'   => $data['id_produksi'],
                                'id_jenis_kayu' => $idJenisKayu,
                                'jumlah_batang' => $record->jumlah_batang,
                            ]);

                            \Illuminate\Support\Facades\Log::channel('single')->info('Lahan Siap Digunakan', [
                                'id_tempat_kayu'  => $record->id,
                                'id_lahan'        => $record->id_lahan,
                                'id_produksi'     => $data['id_produksi'],
                                'diserahkan_oleh' => $data['diserahkan_oleh'],
                                'jumlah_batang'   => $record->jumlah_batang,
                                'kubikasi'        => $record->kubikasi,
                            ]);
                        });

                        Notification::make()
                            ->title('Kayu berhasil diserahkan ke produksi rotary')
                            ->body('Penggunaan lahan telah tercatat.')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
