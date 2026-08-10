<?php

namespace App\Services\AbsensiSources;

use Illuminate\Support\Collection;

interface AbsensiSourceInterface
{
    public function key(): string;

    public function label(): string;

    /**
     * @param  string  $tanggal  Format Y-m-d, wajib (filter per hari)
     */
    public function fetch(string $tanggal): Collection;
}
