<?php

namespace App\Services;

use App\Concerns\FlexibleLogSearch;
use App\Data\HppTriplekJadiLogFilters;
use App\Models\HppTriplekJadiLog;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;

class HppTriplekJadiLogQuery
{
    use FlexibleLogSearch;

    public const PER_PAGE = 50;

    public function filtered(HppTriplekJadiLogFilters $filters): Builder
    {
        return HppTriplekJadiLog::query()
            ->when($filters->tanggalMulai !== '', fn(Builder $q) => $q->where('tanggal', '>=', $filters->tanggalMulai))
            ->when($filters->tanggalSelesai !== '', fn(Builder $q) => $q->where('tanggal', '<=', $filters->tanggalSelesai))
            ->when($filters->jenisKayu !== '', fn(Builder $q) => $q->where('id_jenis_kayu', $filters->jenisKayu))
            ->when($filters->panjang !== '', fn(Builder $q) => $q->where('panjang', $filters->panjang))
            ->when($filters->tebal !== '', fn(Builder $q) => $q->where('tebal', $filters->tebal))
            ->when(
                trim($filters->kw) !== '',
                fn(Builder $q) => $this->flexibleSearch($q, $filters->kw, $this->logSearchConfig())
            );
    }

    public function listQuery(HppTriplekJadiLogFilters $filters): Builder
    {
        return $this->filtered($filters)
            ->select([
                'id',
                'id_jenis_kayu',
                'panjang',
                'lebar',
                'tebal',
                'kw_grade',
                'tanggal',
                'tipe_transaksi',
                'keterangan',
                'total_lembar',
                'total_kubikasi',
                'stok_lembar_before',
                'stok_lembar_after',
                'stok_kubikasi_before',
                'stok_kubikasi_after',
            ])
            ->with('jenisKayu:id,nama_kayu')
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public function paginate(HppTriplekJadiLogFilters $filters, int $perPage = self::PER_PAGE): Paginator
    {
        return $this->listQuery($filters)->simplePaginate($perPage);
    }

    public function aggregateQuery(HppTriplekJadiLogFilters $filters): QueryBuilder
    {
        return $this->filtered($filters)
            ->toBase()
            ->selectRaw("
                COUNT(*) AS entry_count,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'masuk'  THEN total_lembar END), 0)   AS sheets_in,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'keluar' THEN total_lembar END), 0)   AS sheets_out,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'masuk'  THEN total_kubikasi END), 0) AS volume_in,
                COALESCE(SUM(CASE WHEN tipe_transaksi = 'keluar' THEN total_kubikasi END), 0) AS volume_out
            ");
    }

    public function latestQuery(HppTriplekJadiLogFilters $filters): Builder
    {
        return $this->filtered($filters)
            ->select(['id', 'hpp_average', 'stok_lembar_after', 'stok_kubikasi_after'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id');
    }

    public const SUMMARY_CACHE_TTL_MINUTES = 5;

    /** @return array<string, int|float|bool|null> */
    public function summary(HppTriplekJadiLogFilters $filters, bool $useCache = true): array
    {
        if (! $useCache) {
            return $this->computeSummary($filters);
        }

        return Cache::remember(
            $this->summaryCacheKey($filters),
            now()->addMinutes(self::SUMMARY_CACHE_TTL_MINUTES),
            fn(): array => $this->computeSummary($filters)
        );
    }

    /** @return array<string, int|float|bool|null> */
    private function computeSummary(HppTriplekJadiLogFilters $filters): array
    {
        $totals = $this->aggregateQuery($filters)->first();
        $latest = $this->latestQuery($filters)->first();

        $sheetsIn = (int) $totals->sheets_in;
        $sheetsOut = (int) $totals->sheets_out;

        return [
            'entry_count'         => (int) $totals->entry_count,
            'sheets_in'           => $sheetsIn,
            'sheets_out'          => $sheetsOut,
            'sheets_balance'      => $sheetsIn - $sheetsOut,
            'volume_balance'      => (float) $totals->volume_in - (float) $totals->volume_out,
            'latest_hpp_average'  => $latest?->hpp_average,
            'latest_stock_sheets' => $latest?->stok_lembar_after,
            'latest_stock_volume' => $latest?->stok_kubikasi_after,
            'has_logs'            => $latest !== null,
        ];
    }

    private function summaryCacheKey(HppTriplekJadiLogFilters $filters): string
    {
        $version = Cache::get(HppTriplekJadiLog::SUMMARY_CACHE_VERSION_KEY, '0');

        return sprintf(
            'hpp_triplek_jadi_log.summary.%s.%s',
            $version,
            md5(json_encode(get_object_vars($filters)))
        );
    }

    protected function logSearchConfig(): array
    {
        return [
            'text'      => ['keterangan', 'tipe_transaksi'],
            'kw'        => 'kw_grade',
            'relations' => ['jenisKayu.nama_kayu'],
            'size'      => ['panjang', 'lebar', 'tebal'],
            'date'      => 'tanggal',
        ];
    }
}
