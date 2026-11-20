<?php

use App\Data\HppTriplekJadiLogFilters;
use App\Models\HppTriplekJadiLog;
use App\Models\JenisKayu;
use App\Services\HppTriplekJadiLogQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLog(int $woodTypeId, string $type, int $sheets): HppTriplekJadiLog
{
    return HppTriplekJadiLog::create([
        'id_jenis_kayu' => $woodTypeId,
        'panjang' => 244,
        'lebar' => 122,
        'tebal' => 3,
        'kw_grade' => 'KW1',
        'tanggal' => now()->toDateString(),
        'tipe_transaksi' => $type,
        'total_lembar' => $sheets,
        'total_kubikasi' => $sheets * 0.0089,
        'hpp_average' => 100000,
        'stok_lembar_before' => 0,
        'stok_lembar_after' => $sheets,
    ]);
}

it('cached summary equals uncached summary', function () {
    $woodTypeId = JenisKayu::factory()->create()->id;
    makeLog($woodTypeId, 'masuk', 100);
    makeLog($woodTypeId, 'keluar', 30);

    $query = app(HppTriplekJadiLogQuery::class);
    $filters = new HppTriplekJadiLogFilters();

    expect($query->summary($filters))->toEqual($query->summary($filters, useCache: false));
    expect($query->summary($filters)['sheets_balance'])->toBe(70);
});

it('invalidates cache when a log is created', function () {
    $woodTypeId = JenisKayu::factory()->create()->id;
    makeLog($woodTypeId, 'masuk', 100);

    $query = app(HppTriplekJadiLogQuery::class);
    $filters = new HppTriplekJadiLogFilters();
    $query->summary($filters); // isi cache

    makeLog($woodTypeId, 'masuk', 50);

    expect($query->summary($filters)['sheets_in'])->toBe(150);
});
