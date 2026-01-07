<?php

namespace App\Filament\Resources\KayuMasuks\RelationManagers;

use App\Filament\Resources\DetailKayuMasuks\Schemas\DetailKayuMasukForm;
use App\Filament\Resources\DetailKayuMasuks\Tables\DetailKayuMasuksTable;
use App\Models\DetailKayuMasuk;
use App\Models\DetailTurunKayu;
use App\Models\Lahan;         // Import Model Lahan
use App\Models\JenisKayu;     // Import Model JenisKayu
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Actions\Action;       // Action Custom
use Filament\Actions\CreateAction; // Action Create Bawaan
use Illuminate\Contracts\View\View;       // Untuk View Modal

class DetailMasukanKayuRelationManager extends RelationManager
{
    protected static string $relationship = 'DetailMasukanKayu';
    protected static ?string $title = 'Detail Kayu Masuk';

    // Listener agar tabel refresh otomatis setelah sync dari offline mode
    protected $listeners = ['refreshDatatable' => '$refresh'];

    public function isReadOnly(): bool
    {
        return false;
    }

    public static function canViewForRecord($ownerRecord, $pageClass): bool
    {
        // Ambil status dari detail_turun_kayus berdasarkan kayu masuk
        $detailTurun = DetailTurunKayu::where('id_kayu_masuk', $ownerRecord->id)->first();

        // Jika belum ada record → anggap belum selesai → tidak boleh isi
        if (!$detailTurun) {
            return false;
        }

        // Jika status "menunggu" → boleh isi data
        if ($detailTurun->status === 'menunggu') {
            return true;
        }

        // Jika status "selesai" → tampilkan saja (logic asli Anda return true)
        return true;
    }

    public function form(Schema $schema): Schema
    {
        return DetailKayuMasukForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        // Kita panggil konfigurasi tabel eksternal, lalu kita chain headerActions
        return DetailKayuMasuksTable::configure($table)
            ->headerActions([

                // 1. Tombol Create Standar (Online)
                CreateAction::make(),

                // 2. Tombol Total Kubikasi (Punya Anda)
                Action::make('total_kubikasi')
                    ->label(function () {
                        $parentId = $this->ownerRecord?->id;

                        // Hitung kubikasi manual di sini
                        $total = DetailKayuMasuk::where('id_kayu_masuk', $parentId)
                            ->get()
                            ->sum(function ($item) {
                                // Rumus: D * D * P * Jml * 0.785 / 1juta
                                $d = $item->diameter ?? 0;
                                $p = $item->panjang ?? 0; // Asumsi panjang juga dikalikan
                                $jml = $item->jumlah_batang ?? 0;

                                return ($p * $d * $d * $jml * 0.785) / 1000000;
                            });

                        return 'Total: ' . number_format($total, 4, ',', '.') . ' m³';
                    })
                    ->disabled()
                    ->icon('heroicon-o-cube')
                    ->color('gray'),

                // 3. TOMBOL INPUT MODE OFFLINE (Baru)
                Action::make('offlineInput')
                    ->label('Input Mode Offline')
                    ->icon('heroicon-m-signal-slash')
                    ->color('warning')
                    ->modalHeading('Input Kayu (Mode Offline)')
                    ->modalWidth('2xl')

                    ->modalContent(function ($livewire): View {
                        $owner = $livewire->getOwnerRecord();

                        return view('filament.components.offline-detail-kayu-modal', [
                            'parentId'     => $owner->id,

                            // PERUBAHAN DI SINI
                            'optionsLahan' => Lahan::all()->mapWithKeys(fn($lahan) => [
                                $lahan->id => "{$lahan->kode_lahan} - {$lahan->nama_lahan}"
                            ]),

                            'optionsJenis' => JenisKayu::pluck('nama_kayu', 'id'),
                        ]);
                    })

                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->extraAttributes(['id' => 'modal-offline-input']),
            ]);
    }

    // Fungsi helper increment/decrement (Biarkan tetap ada)
    public function incrementJumlah($id)
    {
        if ($item = DetailKayuMasuk::find($id)) {
            $item->increment('jumlah_batang');
        }
    }

    public function decrementJumlah($id)
    {
        if ($item = DetailKayuMasuk::find($id)) {
            if ($item->jumlah_batang > 0) {
                $item->decrement('jumlah_batang');
            }
        }
    }
}
