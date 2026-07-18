<?php

namespace App\Filament\Resources\HasilTerimaGudangSatus\Tables;

use App\Models\JenisKayu;
use App\Models\SerahTerimaGudangSatu;
use App\Services\StokPlywoodSiapJualService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HasilTerimaGudangSatusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grade.nama_grade')
                    ->label('Grade')
                    ->getStateUsing(
                        fn ($record) => ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        .' | '.
                        ($record->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ukuran.dimensi')
                    ->label('Ukuran')
                    ->getStateUsing(fn ($record) => $record->ukuran?->dimensi ?? '-')
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('ket')
                    ->label('Keterangan')
                    ->searchable(),

                TextColumn::make('status_serah')
                    ->label('Status Serah')
                    ->getStateUsing(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'Belum Diserahkan';
                        }

                        return $serah->diterima_oleh === '-' ? 'Menunggu Diterima' : 'Diterima';
                    })
                    ->badge()
                    ->color(function ($record) {
                        $serah = $record->serahTerimaGudangSatu;

                        if (! $serah) {
                            return 'gray';
                        }

                        return $serah->diterima_oleh === '-' ? 'warning' : 'success';
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([

                // 🚚 TOMBOL SERAH
                Action::make('serah')
                    ->label('Serah')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn ($record) => ! $record->serahTerimaGudangSatu)
                    ->modalHeading('Serah Barang')
                    ->modalDescription(fn ($record) => 'Jumlah: '.($record->jumlah ?? 0).' pcs. Pilih tujuan penyerahan barang ini.')
                    ->modalSubmitActionLabel('Serah')
                    ->form(function ($record) {
                        return [
                            Placeholder::make('grade_detail')
                                ->label('Grade')
                                ->content(
                                    ($record->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                                    .' | '.
                                    ($record->grade?->nama_grade ?? '-')
                                ),

                            Placeholder::make('jenis_detail')
                                ->label('Jenis Barang')
                                ->content($record->jenisBarang?->nama_jenis_barang ?? '-'),

                            Placeholder::make('ukuran_detail')
                                ->label('Ukuran')
                                ->content($record->ukuran?->dimensi ?? '-'),

                            Placeholder::make('jumlah_detail')
                                ->label('Jumlah')
                                ->content((string) ($record->jumlah ?? 0)),

                            Radio::make('serah_ke')
                                ->label('Serah Ke')
                                ->options([
                                    'nyusup' => 'Serah ke Nyusup',
                                    'gudang' => 'Serah ke Gudang',
                                ])
                                ->default('nyusup')
                                ->required()
                                ->reactive(),
                        ];
                    })
                    ->action(function ($record, array $data) {

                        if ($data['serah_ke'] === 'nyusup') {

                            try {
                                SerahTerimaGudangSatu::create([
                                    'id_hasil_terima_gudang_satu' => $record->id,
                                    'tujuan' => 'nyusup',
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Menunggu',
                                ]);

                                Notification::make()
                                    ->title('Berhasil diserahkan (Nyusup)')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }

                        } elseif ($data['serah_ke'] === 'gudang') {

                            try {
                                DB::transaction(function () use ($record) {

                                    $panjang = $record->ukuran?->panjang ?? 0;
                                    $lebar = $record->ukuran?->lebar ?? 0;
                                    $tebal = $record->ukuran?->tebal ?? 0;
                                    $kwGrade = $record->grade?->nama_grade ?? '-';

                                    $namaJenisBarang = $record->jenisBarang?->nama_jenis_barang;

                                    $idJenisKayu = JenisKayu::where('nama_kayu', $namaJenisBarang)
                                        ->value('id');

                                    if (! $idJenisKayu) {
                                        throw new \RuntimeException("Jenis kayu \"{$namaJenisBarang}\" tidak ditemukan di master jenis kayu.");
                                    }

                                    $lembar = $record->jumlah ?? 0;
                                    $penyerah = Auth::user()->name;

                                    // 1. Catat serah terima dengan tujuan 'gudang'.
                                    // Belum ada fitur "terima" untuk tujuan gudang, jadi
                                    // langsung ditandai diterima oleh pengirim sendiri (auto-terima).
                                    $serahTerima = SerahTerimaGudangSatu::create([
                                        'id_hasil_terima_gudang_satu' => $record->id,
                                        'tujuan' => 'gudang',
                                        'diserahkan_oleh' => $penyerah,
                                        'diterima_oleh' => $penyerah,
                                        'status' => 'Diterima',
                                    ]);

                                    // 2. Tambah stok plywood siap jual + catat log (kubikasi dihitung di dalam service)
                                    app(StokPlywoodSiapJualService::class)->tambah(
                                        idJenisKayu: $idJenisKayu,
                                        panjang: $panjang,
                                        lebar: $lebar,
                                        tebal: $tebal,
                                        kwGrade: $kwGrade,
                                        lembar: $lembar,
                                        keterangan: 'Serah terima dari Terima Gudang Satu ke Gudang',
                                        referensi: $serahTerima,
                                    );
                                });

                                Notification::make()
                                    ->title('Barang berhasil diserahkan ke Gudang dan stok berhasil ditambahkan')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Gagal menyerahkan barang ke Gudang')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }
                    }),

                EditAction::make()
                    ->hidden(function ($record, $livewire) {
                        // Sembunyikan kalau sudah divalidasi
                        if ($livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi') {
                            return true;
                        }

                        // Sembunyikan kalau sudah DITERIMA (bukan cuma diserahkan)
                        $serah = $record->serahTerimaGudangSatu;

                        return $serah && $serah->diterima_oleh !== '-';
                    }),

                DeleteAction::make()
                    ->hidden(
                        fn ($record, $livewire) => $record->serahTerimaGudangSatu
                        || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
