<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;
use App\Models\PegawaiTerimaGudangSatu;

class ProduksiTerimaGudangSatuSummaryWidget extends Widget
{
    protected string $view = 'filament.resources.produksi-terima-gudang-satu.widgets.summary';

    public ?Model $record = null;
    public array $summary = [];
    protected int | string | array $columnSpan = 'full';

    public function getListeners(): array
    {
        $id = $this->record?->id;
        if (!$id) return [];

        return [
            "echo:production.terima_gudang_satu.{$id},.ProductionUpdated" => 'refreshSummary',
        ];
    }

    public function mount(?Model $record = null): void
    {
        $this->record = $record;
        $this->refreshSummary();
    }

    public function refreshSummary(): void
    {
        if (!$this->record) return;

        $produksiId = $this->record->id;

        $totalPegawai = PegawaiTerimaGudangSatu::where('id_produksi_terima_gudang_satu', $produksiId)
            ->distinct('id_pegawai')
            ->count('id_pegawai');

        $this->summary = [
            'totalPegawai' => $totalPegawai,
        ];
    }
}
