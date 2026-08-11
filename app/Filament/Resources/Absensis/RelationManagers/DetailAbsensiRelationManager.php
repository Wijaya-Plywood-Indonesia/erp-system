<?php

namespace App\Filament\Resources\Absensis\RelationManagers;

use App\Filament\Pages\Absen;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class DetailAbsensiRelationManager extends RelationManager
{
    protected static string $relationship = 'detailAbsensis';

    public function form(Schema $schema): Schema
    {
        return $schema([]);
    }

    public function sinkronKanData(): void
    {
        try {
            $ownerRecord = $this->getOwnerRecord(); // Mengambil instance Absensi utama
            $targetDate = \Carbon\Carbon::parse($ownerRecord->tanggal)->format('Y-m-d');

            // Panggil instance Absen Page untuk menjalankan sinkronisasi
            $absenPage = app(Absen::class);
            $absenPage->data['tanggal'] = $targetDate;

            // Eksekusi pemuatan/proses sinkronisasi data
            $absenPage->loadData();

            Notification::make()
                ->success()
                ->title('Berhasil Disinkronkan!')
                ->body('Data absensi pegawai berhasil diperbarui untuk tanggal ' . \Carbon\Carbon::parse($targetDate)->format('d/m/Y'))
                ->send();

            // Refresh tabel relation manager agar data terbaru langsung terlihat
            $this->dispatch('refreshRelationManager');
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Sinkronisasi')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->send();
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nama_pegawai')
            ->defaultSort('kode_pegawai', 'asc')
            ->paginated(false)
            ->columns([
                TextColumn::make('kode_pegawai')
                    ->label('Kode Pegawai')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nama_pegawai')
                    ->label('Nama Pegawai')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jam_masuk')
                    ->label('Jam Masuk')
                    ->time('H:i:s')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->placeholder('Tidak Absen')
                    ->sortable(),

                TextColumn::make('jam_pulang')
                    ->label('Jam Pulang')
                    ->time('H:i:s')
                    ->badge()
                    ->color(fn($state) => $state ? 'danger' : 'gray')
                    ->placeholder('-- : -- : --')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn($state) => $state === 'Tidak Absen' ? 'gray' : 'success'),
            ])
            ->headerActions([

                Action::make('preview_report')
                    ->label('Preview Jam Finger')
                    ->icon('heroicon-o-eye')
                    ->color('warning')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(function () {
                        $targetDate = \Carbon\Carbon::parse($this->getOwnerRecord()->tanggal)->format('Y-m-d');

                        $absenPage = app(Absen::class);
                        $absenPage->data['tanggal'] = $targetDate;
                        $absenPage->loadData();

                        $listAbsensi = $absenPage->listAbsensi;

                        // 🌟 Urutkan secara numerik menaik (1, 2, 3... 100, dst)
                        usort($listAbsensi, function ($a, $b) {
                            return (int) ($a['kodep'] ?? 0) <=> (int) ($b['kodep'] ?? 0);
                        });

                        return view('filament.resources.absensis.preview-report', [
                            'listAbsensi' => $listAbsensi,
                            'listUnregistered' => $absenPage->listUnregistered,
                        ]);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // AMAN sekarang — basisnya memang DetailAbsensi, bukan Pegawai
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
