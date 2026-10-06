<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\AbsensiSources\AbsensiSourceInterface;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use App\Services\DashboardPengawas\Traits\HasAbsenAndPotongan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigurableDashboardSource implements DashboardSourceInterface
{
    use HasAbsenAndPotongan;

    protected string $label;

    protected ?string $produksiTable;

    protected ?string $hasilTable;

    protected ?string $sumCol;

    protected ?string $hasilFk;

    protected bool $hasKw;

    protected string $satuan;

    public function __construct(
        string $label,
        ?string $produksiTable,
        ?string $hasilTable,
        ?string $sumCol,
        ?string $hasilFk,
        bool $hasKw = false,
        string $satuan = 'Lembar',
        ?AbsensiSourceInterface $absensiSource = null
    ) {
        $this->label = $label;
        $this->produksiTable = $produksiTable;
        $this->hasilTable = $hasilTable;
        $this->sumCol = $sumCol;
        $this->hasilFk = $hasilFk;
        $this->hasKw = $hasKw;
        $this->satuan = $satuan;
        $this->absensiSource = $absensiSource;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function canAccess(User $user): bool
    {
        return $this->bolehAkses($user);
    }

    protected function kosong(): array
    {
        return ['total' => 0, 'satuan' => $this->satuan, 'detail' => []];
    }

    protected function getDateColumn(): string
    {
        foreach (['tanggal_produksi', 'tanggal', 'tgl_produksi'] as $col) {
            if (Schema::hasColumn($this->produksiTable, $col)) {
                return $col;
            }
        }

        return 'created_at';
    }

    public function getProduksi(string $tanggal): array
    {
        if (! $this->produksiTable || ! $this->hasilTable || ! $this->sumCol || ! $this->hasilFk) {
            return $this->kosong();
        }

        try {
            $dateCol = $this->getDateColumn();
            $query = DB::table($this->hasilTable)
                ->join($this->produksiTable, $this->produksiTable.'.id', '=', $this->hasilTable.'.'.$this->hasilFk)
                ->whereDate($this->produksiTable.'.'.$dateCol, $tanggal);

            $total = (clone $query)->sum($this->hasilTable.'.'.$this->sumCol);

            $detail = [];
            if ($this->hasKw) {
                $detail = (clone $query)
                    ->selectRaw($this->hasilTable.'.kw, SUM(CAST('.$this->hasilTable.'.'.$this->sumCol.' AS UNSIGNED)) as jumlah')
                    ->groupBy($this->hasilTable.'.kw')
                    ->get()
                    ->map(fn ($row) => [
                        'nama' => 'Kw '.$row->kw,
                        'jumlah' => $row->jumlah,
                    ])
                    ->toArray();
            }

            return [
                'total' => $total,
                'satuan' => $this->satuan,
                'detail' => $detail,
            ];
        } catch (\Exception $e) {
            return $this->kosong();
        }
    }

    public function getSerahTerima(string $tanggal): array
    {
        if (! $this->produksiTable || ! $this->hasilTable || ! $this->sumCol || ! $this->hasilFk) {
            return $this->kosong();
        }

        try {
            $dateCol = $this->getDateColumn();

            $base = fn () => DB::table($this->hasilTable)
                ->join($this->produksiTable, $this->produksiTable.'.id', '=', $this->hasilTable.'.'.$this->hasilFk)
                ->whereDate($this->produksiTable.'.'.$dateCol, $tanggal);

            if (Schema::hasColumn($this->hasilTable, 'diserahkan_at')) {
                $total = $base()
                    ->whereNotNull($this->hasilTable.'.diserahkan_at')
                    ->sum($this->hasilTable.'.'.$this->sumCol);

                return ['total' => $total, 'satuan' => $this->satuan, 'detail' => []];
            }

            if (Schema::hasColumn($this->hasilTable, 'status')) {
                $total = $base()
                    ->whereIn($this->hasilTable.'.status', ['diserahkan', 'selesai'])
                    ->sum($this->hasilTable.'.'.$this->sumCol);

                return ['total' => $total, 'satuan' => $this->satuan, 'detail' => []];
            }
        } catch (\Exception $e) {
            // abaikan, kembalikan 0
        }

        return $this->kosong();
    }

    public function getPegawai(string $tanggal): array
    {
        if ($this->absensiSource) {
            return [
                'total' => $this->absensiSource->fetch($tanggal)->count(),
                'list' => [],
            ];
        }

        return ['total' => 0, 'list' => []];
    }
}
