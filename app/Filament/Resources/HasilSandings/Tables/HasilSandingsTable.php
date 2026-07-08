<?php

namespace App\Filament\Resources\HasilSandings\Tables;

use App\Models\ModalSanding;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\HtmlString;

class HasilSandingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barangSetengahJadiInfo')
                    ->label('Barang Setengah Jadi')
                    ->getStateUsing(function ($record) {
                        $kategori = $record->barangSetengahJadi?->grade?->kategoriBarang?->nama_kategori ?? '-';
                        $ukuran = $record->barangSetengahJadi?->ukuran?->dimensi ?? '-';
                        $grade = $record->barangSetengahJadi?->grade?->nama_grade ?? '-';
                        $jenis = $record->barangSetengahJadi?->jenisBarang?->nama_jenis_barang ?? '-';

                        return "{$kategori} — {$ukuran} - {$jenis} - {$grade}";
                    })
                ,

                TextColumn::make('kuantitas')
                    ->label('Qty')
                    ->sortable(),

                TextColumn::make('jumlah_sanding_face')
                    ->label('Face'),

                TextColumn::make('jumlah_sanding_back')
                    ->label('Back'),

                TextColumn::make('no_palet')
                    ->label('Palet')
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                //--- INI BUAT FILTER AJA
                TextColumn::make('barangSetengahJadi.grade.kategoriBarang.nama_kategori')
                    ->label('Kategori')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.ukuran.dimensi')
                    ->label('Ukuran')
                    ->searchable(query: function ($query, $search) {
                        $query->whereHas('barangSetengahJadi.ukuran', function ($q) use ($search) {
                            $q->whereRaw("CONCAT(panjang, ' x ', lebar, ' x ', tebal) LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true)
                ,

                TextColumn::make('barangSetengahJadi.grade.nama_grade')
                    ->label('Grade')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('barangSetengahJadi.jenisBarang.nama_jenis_barang')
                    ->label('Jenis Barang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('label_tujuan_serah')
    ->label('Diserahkan Ke')
    ->badge()
    ->color(fn ($record) => $record->tujuan_serah ? 'success' : 'gray'),

            ])

            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->recordActions([
                // Edit Action — HILANG jika status sudah divalidasi

                Action::make('serahkan')
    ->label('Serahkan')
    ->icon('heroicon-o-arrow-right-circle')
    ->color('success')
    ->visible(fn ($record) => $record->diserahkan_at === null)
    ->schema(function ($record) {
        $record->loadMissing(['barangSetengahJadi.ukuran', 'barangSetengahJadi.jenisBarang', 'barangSetengahJadi.grade']);

        $bsj    = $record->barangSetengahJadi;
        $ukuran = $bsj?->ukuran;

        $dimensi = $ukuran
            ? ($ukuran->panjang + 0) . ' × ' . ($ukuran->lebar + 0) . ' × ' . ($ukuran->tebal + 0)
            : '-';

        return [
            Grid::make(2)->schema([
                Placeholder::make('info_barang')
                    ->label('Barang')
                    ->content($bsj?->jenisBarang?->nama_jenis_barang ?? '-'),
                Placeholder::make('info_grade')
                    ->label('Grade')
                    ->content($bsj?->grade?->nama_grade ?? '-'),
                Placeholder::make('info_ukuran')
                    ->label('Ukuran (P × L × T)')
                    ->content($dimensi),
                Placeholder::make('info_palet')
                    ->label('No. Palet')
                    ->content((string) ($record->no_palet ?? '-')),
                Placeholder::make('info_qty')
                    ->label('Kuantitas')
                    ->content(new HtmlString('<strong>' . number_format((float) $record->kuantitas) . ' lembar</strong>')),
                Placeholder::make('info_status')
                    ->label('Status Sanding')
                    ->content((string) ($record->status ?? '-')),
            ]),

            Radio::make('tujuan_serah')
                ->label('Serahkan ke')
                ->options([
                    'platform_jadi' => 'Gudang Platform Jadi',
                    'triplek_jadi'  => 'Gudang Triplek Jadi',
                ])
                ->default('platform_jadi')
                ->required(),
        ];
    })
    ->modalHeading(fn ($record) => 'Serahkan Hasil Sanding — Palet ' . $record->no_palet)
    ->modalSubmitActionLabel('Serahkan')
    ->modalWidth('md')
    ->requiresConfirmation(false)
    ->action(function ($record, array $data) {
        // ── VALIDASI ────────────────────────────────────────────────
        // 1. Ambil ulang dari DB (hindari race: dua orang buka modal bersamaan)
        $fresh = $record->fresh(['barangSetengahJadi.ukuran']);

        if ($fresh->diserahkan_at !== null) {
            Notification::make()->warning()
                ->title('Palet ini sudah diserahkan')
                ->body('Diserahkan ke ' . $fresh->label_tujuan_serah . '. Muat ulang halaman untuk melihat status terbaru.')
                ->send();
            return;
        }

        // 2. Kuantitas harus valid
        if ((float) $fresh->kuantitas <= 0) {
            Notification::make()->danger()
                ->title('Kuantitas tidak valid')
                ->body('Kuantitas palet ini 0. Perbaiki data hasil sanding sebelum diserahkan.')
                ->send();
            return;
        }

        // 3. Data barang & ukuran harus lengkap (dibutuhkan gudang saat Terima)
        if (! $fresh->barangSetengahJadi || ! $fresh->barangSetengahJadi->ukuran) {
            Notification::make()->danger()
                ->title('Data barang tidak lengkap')
                ->body('Barang setengah jadi / ukuran tidak ditemukan. Perbaiki data sebelum diserahkan.')
                ->send();
            return;
        }

        // 4. Tujuan harus salah satu nilai yang sah
        if (! in_array($data['tujuan_serah'] ?? null, ['platform_jadi', 'triplek_jadi'], true)) {
            Notification::make()->danger()->title('Tujuan serah tidak valid.')->send();
            return;
        }

        // ── SIMPAN ──────────────────────────────────────────────────
        $fresh->update([
            'tujuan_serah'    => $data['tujuan_serah'],
            'diserahkan_oleh' => auth()->id(),
            'diserahkan_at'   => now(),
        ]);

        Notification::make()->success()
            ->title('Palet ' . $fresh->no_palet . ' diserahkan')
            ->body(number_format((float) $fresh->kuantitas) . ' lembar → '
                . ($data['tujuan_serah'] === 'platform_jadi' ? 'Gudang Platform Jadi' : 'Gudang Triplek Jadi'))
            ->send();
    }),
                EditAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),

                // Delete Action — HILANG jika status sudah divalidasi
                DeleteAction::make()
                    ->hidden(
                        fn($livewire) =>
                        $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(
                            fn($livewire) =>
                            $livewire->ownerRecord?->validasiTerakhir?->status === 'divalidasi'
                        ),
                ]),
            ]);
    }
}
