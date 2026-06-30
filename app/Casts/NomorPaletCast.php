<?php

namespace App\Casts;

use App\Models\DetailHasilPaletRotary;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Cache;

class NomorPaletCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): string
    {
        if (!$value || $value == 0) return 'AF';
        if ($value < 0) return 'AF-' . abs($value);

        return Cache::remember("kode_palet_{$value}", 60, function () use ($value, $model) {

            if ($model->relationLoaded('detailPaletRotary') && $model->detailPaletRotary) {
                return $model->detailPaletRotary->kode_palet;
            }
            $detail = DetailHasilPaletRotary::with('produksi.mesin')->find($value);
            return $detail?->kode_palet ?? (string) $value;
        });
    }

    public function set($model, string $key, $value, array $attributes): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
