<?php

namespace App\Filament\Resources\HasilGrajiTripleks\Tables;

use App\Models\HasilGrajiTriplek;
use App\Models\JenisKayu;
use App\Models\SerahTerimaHp;
use App\Services\StokTriplekJadiService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class HasilGrajiTripleksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'barangSetengahJadiHp.jenisBarang',
                'barangSetengahJadiHp.grade.kategoriBarang',
                'barangSetengahJadiHp.ukuran',
                'serahTerimaHp',
            ]))
            ->columns([

                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(function ($record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'gray';
                        }

                        return $serahTerima->diterima_oleh === '-' ? 'warning' : 'success';
                    })
                    ->description(function ($record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'Belum Serah';
                        }

                        $tujuan = match ($serahTerima->tujuan) {
                            'gudang' => 'Gudang',
                            'sanding' => 'Sanding',
                            default => '-',
                        };

                        return $serahTerima->diterima_oleh === '-'
                            ? "Menunggu Diterima {$tujuan}"
                            : "Sudah Diterima {$tujuan}";
                    }),

                TextColumn::make('barangSetengahJadiHp.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('grade_display')
                    ->label('Grade')
                    ->getStateUsing(fn ($record) => ($record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori')
                        .' | '.
                        ($record->barangSetengahJadiHp?->grade?->nama_grade ?? '-')
                    )
                    ->sortable(),

                TextColumn::make('barangSetengahJadiHp.ukuran.nama_ukuran')
                    ->label('Ukuran')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Jumlah')
                    ->alignCenter(),

                /*
                 * STATUS SERAH — toggleable, default hidden
                 */
                TextColumn::make('status_serah')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (HasilGrajiTriplek $record) {
                        $serahTerima = $record->serahTerimaHp;

                        if (! $serahTerima) {
                            return 'Belum Diserahkan';
                        }

                        $tujuan = match ($serahTerima->tujuan) {
                            'gudang' => 'Gudang',
                            'sanding' => 'Sanding',
                            default => '-',
                        };

                        return $serahTerima->diterima_oleh === '-'
                            ? "Menunggu Diterima {$tujuan}"
                            : "Sudah Diterima {$tujuan}";
                    })
                    ->color(fn ($state) => match (true) {
                        str_contains($state, 'Sudah Diterima') => 'success',
                        str_contains($state, 'Menunggu Diterima') => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DISERAHKAN OLEH — toggleable, default hidden
                 */
                TextColumn::make('serahTerimaHp.diserahkan_oleh')
                    ->label('Diserahkan Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * DITERIMA OLEH — toggleable, default hidden
                 */
                TextColumn::make('serahTerimaHp.diterima_oleh')
                    ->label('Diterima Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * CREATED AT — toggleable, default hidden
                 */
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                /*
                 * UPDATED AT — toggleable, default hidden
                 */
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->headerActions([
                CreateAction::make()
                    ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])

            ->recordActions([
                Action::make('serah')
                    ->label('Serah')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (HasilGrajiTriplek $record) => ! $record->serahTerimaHp)
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Hasil Graji Triplek')
                    ->modalDescription('Pastikan data berikut sudah sesuai sebelum diserahkan.')
                    ->modalContent(function (HasilGrajiTriplek $record) {
                        $jenis = $record->barangSetengahJadiHp?->jenisBarang?->nama_jenis_barang ?? '-';
                        $kategori = $record->barangSetengahJadiHp?->grade?->kategoriBarang?->nama_kategori ?? 'Tanpa Kategori';
                        $grade = $record->barangSetengahJadiHp?->grade?->nama_grade ?? '-';
                        $ukuran = $record->barangSetengahJadiHp?->ukuran?->nama_ukuran ?? '-';

                        return new HtmlString(<<<HTML
            <div class="space-y-2 text-sm">
                <div class="grid grid-cols-3 gap-1">
                    <span class="font-medium text-gray-500">No. Palet</span>
                    <span class="col-span-2">: {$record->no_palet}</span>

                    <span class="font-medium text-gray-500">Jenis Barang</span>
                    <span class="col-span-2">: {$jenis}</span>

                    <span class="font-medium text-gray-500">Grade</span>
                    <span class="col-span-2">: {$kategori} | {$grade}</span>

                    <span class="font-medium text-gray-500">Ukuran</span>
                    <span class="col-span-2">: {$ukuran}</span>

                    <span class="font-medium text-gray-500">Jumlah Lembar</span>
                    <span class="col-span-2">: {$record->isi}</span>
                </div>
            </div>
        HTML);
                    })
                    ->schema([
                        Radio::make('tujuan')
                            ->label('Diterima Di Mana')
                            ->options([
                                'sanding' => 'Sanding',
                                'gudang' => 'Gudang',
                            ])
                            ->default('sanding')
                            ->required()
                            ->inline(false)
                            ->extraFieldWrapperAttributes(['class' => 'flex flex-col gap-2']),
                    ])
                    ->action(function (HasilGrajiTriplek $record, array $data) {
                        try {
                            DB::transaction(function () use ($record, $data) {
                                if ($data['tujuan'] === 'gudang') {
                                    $barang = $record->barangSetengahJadiHp;
                                    $ukuran = $barang?->ukuran;
                                    $grade = $barang?->grade;
                                    $namaJenis = $barang?->jenisBarang?->nama_jenis_barang;

                                    $jenisKayu = JenisKayu::where('nama_kayu', $namaJenis)->first();

                                    if (! $jenisKayu) {
                                        throw new \RuntimeException("Jenis kayu \"{$namaJenis}\" tidak ditemukan di master Jenis Kayu. Tambahkan dulu di master data.");
                                    }

                                    app(StokTriplekJadiService::class)->tambah(
                                        idJenisKayu: $jenisKayu->id, // ✅ ID dari tabel jenis_kayus, hasil mapping by nama
                                        panjang: $ukuran?->panjang ?? 0,
                                        lebar: $ukuran?->lebar ?? 0,
                                        tebal: $ukuran?->tebal ?? 0,
                                        kwGrade: $grade?->nama_grade ?? '-',
                                        lembar: $record->isi,
                                        kubikasi: 0, // TODO: isi rumus konversi kubikasi kalau sudah ada
                                        keterangan: "Serah Graji Triplek ke Gudang - No. Palet {$record->no_palet}",
                                        referensi: $record,
                                    );

                                    SerahTerimaHp::create([
                                        'id_hasil_graji_triplek' => $record->id,
                                        'tujuan' => 'gudang',
                                        'diserahkan_oleh' => Auth::user()->name,
                                        'diterima_oleh' => Auth::user()->name,
                                        'status' => 'Serah Graji Triplek',
                                    ]);

                                    return;
                                }

                                SerahTerimaHp::create([
                                    'id_hasil_graji_triplek' => $record->id,
                                    'tujuan' => 'sanding',
                                    'diserahkan_oleh' => Auth::user()->name,
                                    'diterima_oleh' => '-',
                                    'status' => 'Serah Graji Triplek',
                                ]);
                            });

                            $record->unsetRelation('serahTerimaHp');
                            $record->refresh();

                            $tujuanLabel = $data['tujuan'] === 'gudang' ? 'Gudang' : 'Sanding';

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet telah diserahkan ke {$tujuanLabel}.")
                                ->success()
                                ->send();

                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(function ($livewire, HasilGrajiTriplek $record) {
                        $serahTerima = $record->serahTerimaHp;
                        $sudahDiterima = $serahTerima && $serahTerima->diterima_oleh !== '-';

                        return $sudahDiterima
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),

                DeleteAction::make()
                    ->hidden(function ($livewire, HasilGrajiTriplek $record) {
                        return (bool) $record->serahTerimaHp
                            || $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi';
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($livewire) => $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
