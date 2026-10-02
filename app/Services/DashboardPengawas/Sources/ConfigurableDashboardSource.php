<?php

namespace App\Services\DashboardPengawas\Sources;

use App\Models\User;
use App\Services\DashboardPengawas\DashboardSourceInterface;
use Illuminate\Support\Facades\DB;

use App\Services\AbsensiSources\AbsensiSourceInterface;

class ConfigurableDashboardSource implements DashboardSourceInterface
{
    protected string $label;
    protected ?string $produksiTable;
    protected ?string $hasilTable;
    protected ?string $sumCol;
    protected ?string $hasilFk;
    protected bool $hasKw;
    protected string $satuan;
    protected ?AbsensiSourceInterface $absensiSource;

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
        return true; 
    }

    protected function getDateColumn(): string
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->produksiTable, 'tanggal_produksi')) {
            return 'tanggal_produksi';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->produksiTable, 'tanggal')) {
            return 'tanggal';
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn($this->produksiTable, 'tgl_produksi')) {
            return 'tgl_produksi';
        }
        return 'created_at';
    }

    public function getProduksi(string $tanggal): array
    {
        if (!$this->produksiTable || !$this->hasilTable || !$this->sumCol || !$this->hasilFk) {
            return [
                'total'  => 0,
                'satuan' => $this->satuan,
                'detail' => [],
            ];
        }

        try {
            $dateCol = $this->getDateColumn();
            $query = DB::table($this->hasilTable)
                ->join($this->produksiTable, $this->produksiTable . '.id', '=', $this->hasilTable . '.' . $this->hasilFk)
                ->whereDate($this->produksiTable . '.' . $dateCol, $tanggal);
                
            $total = (clone $query)->sum($this->hasilTable . '.' . $this->sumCol);

            $detail = [];
            if ($this->hasKw) {
                $detail = (clone $query)
                    ->selectRaw($this->hasilTable . '.kw, SUM(CAST(' . $this->hasilTable . '.' . $this->sumCol . ' AS UNSIGNED)) as jumlah')
                    ->groupBy($this->hasilTable . '.kw')
                    ->get()
                    ->map(fn ($row) => [
                        'nama'   => 'Kw ' . $row->kw,
                        'jumlah' => $row->jumlah,
                    ])
                    ->toArray();
            }

            return [
                'total'  => $total,
                'satuan' => $this->satuan,
                'detail' => $detail,
            ];
        } catch (\Exception $e) {
            return [
                'total'  => 0,
                'satuan' => $this->satuan,
                'detail' => [],
            ];
        }
    }

    public function getSerahTerima(string $tanggal): array
    {
        if (!$this->produksiTable || !$this->hasilTable || !$this->sumCol || !$this->hasilFk) {
            return [
                'total'  => 0,
                'satuan' => $this->satuan,
                'detail' => [],
            ];
        }

        try {
            $dateCol = $this->getDateColumn();
            
            // Check for serah terima columns
            $hasDiserahkanAt = \Illuminate\Support\Facades\Schema::hasColumn($this->hasilTable, 'diserahkan_at');
            $hasStatus = \Illuminate\Support\Facades\Schema::hasColumn($this->hasilTable, 'status');

            if ($hasDiserahkanAt) {
                $query = DB::table($this->hasilTable)
                    ->join($this->produksiTable, $this->produksiTable . '.id', '=', $this->hasilTable . '.' . $this->hasilFk)
                    ->whereDate($this->produksiTable . '.' . $dateCol, $tanggal)
                    ->whereNotNull($this->hasilTable . '.diserahkan_at');
                
                $total = $query->sum($this->hasilTable . '.' . $this->sumCol);

                return [
                    'total'  => $total,
                    'satuan' => $this->satuan,
                    'detail' => [],
                ];
            } elseif ($hasStatus) {
                $query = DB::table($this->hasilTable)
                    ->join($this->produksiTable, $this->produksiTable . '.id', '=', $this->hasilTable . '.' . $this->hasilFk)
                    ->whereDate($this->produksiTable . '.' . $dateCol, $tanggal)
                    ->whereIn($this->hasilTable . '.status', ['diserahkan', 'selesai']);
                
                $total = $query->sum($this->hasilTable . '.' . $this->sumCol);

                return [
                    'total'  => $total,
                    'satuan' => $this->satuan,
                    'detail' => [],
                ];
            }
        } catch (\Exception $e) {
            // Ignore error and return 0
        }

        return [
            'total'  => 0,
            'satuan' => $this->satuan,
            'detail' => [],
        ];
    }

    public function getPegawai(string $tanggal): array
    {
        if ($this->absensiSource) {
            $rows = $this->absensiSource->fetch($tanggal);
            return [
                'total' => $rows->count(),
                'list'  => []
            ];
        }

        return [
            'total' => 0,
            'list'  => []
        ];
    }
}
