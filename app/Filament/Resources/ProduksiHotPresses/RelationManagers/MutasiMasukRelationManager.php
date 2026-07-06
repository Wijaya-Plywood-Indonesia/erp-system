<?php

namespace App\Filament\Resources\ProduksiHotPresses\RelationManagers;

use App\Models\VeneerJadiMutasiKeluarPalet;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class MutasiMasukRelationManager extends RelationManager
{
    protected static string $relationship = 'mutasiMasuk';
    protected static ?string $title = 'Serah Terima';

    public string $sumberAsal = 'gudang';
    public array $tebal_aktual = [];
    public array $catatan_internal = [];
    public function render(): View
    {
        $ownerRecord = $this->getOwnerRecord();

        // 🌟 CUSTOM QUERY BERDASARKAN FILTER LIVEWIRE
        $records = $ownerRecord->mutasiMasuk()
            ->with(['mutasiKeluar.jenisKayu', 'mutasiKeluar.operator'])
            ->whereHas('mutasiKeluar', function ($query) {
                if ($this->sumberAsal === 'gudang') {
                    $query->whereRaw('LOWER(tujuan) LIKE ?', ['%hotpress%']);
                } else {
                    $query->whereRaw('LOWER(tujuan) LIKE ?', ['%sanding%']);
                }
            })
            ->orderBy('id', 'desc') // Selalu urutkan data terbaru di atas
            ->get();

        return view('filament.resources.produksi-hotpress.pages.serah-terima-custom-page', [
            'records' => $records,
        ]);
    }

    // 🌟 LOGIKA PROSES BACKEND UTK TOMBOL "TERIMA" KUSTOM
    public function terimaMaterialKustom(int $recordId): void
    {
        $record = VeneerJadiMutasiKeluarPalet::findOrFail($recordId);

        // Jika tebal_aktual tidak diisi oleh operator, fallback ke tebal asli dokumen
        $tebalBaru = (float) ($this->tebal_aktual[$recordId] ?? $record->mutasiKeluar->tebal);
        $catatan = $this->catatan_internal[$recordId] ?? '';

        // Hitung Kubikasi Baru
        $kubikasiBaru = ($record->mutasiKeluar->panjang * $record->mutasiKeluar->lebar * $tebalBaru * $record->jumlah_lembar) / 10000000;

        // Susun keterangan update
        $keteranganUpdate = $record->mutasiKeluar->keterangan;
        if ($tebalBaru !== (float)$record->mutasiKeluar->tebal) {
            $keteranganUpdate .= " | Adjust Sanding: Tebal berubah dari {$record->mutasiKeluar->tebal}mm ke {$tebalBaru}mm.";
        }
        if (!empty($catatan)) {
            $keteranganUpdate .= " (" . $catatan . ")";
        }

        // Eksekusi Update ke Database
        $record->update([
            'diterima_by' => Auth::id(),
            'diterima_at' => now(),
            'tebal' => $tebalBaru,
            'stok_kubikasi' => $kubikasiBaru,
            'keterangan' => $keteranganUpdate
        ]);

        // Bersihkan data input setelah submit berhasil
        unset($this->tebal_aktual[$recordId], $this->catatan_internal[$recordId]);

        // Pemicu Notifikasi toast Filament v4
        Notification::make()
            ->success()
            ->title('Material Berhasil Diterima')
            ->body('Stok masuk terdaftar pada antrean produksi Hotpress.')
            ->send();
    }
}
