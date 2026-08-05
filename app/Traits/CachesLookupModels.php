<?php

namespace App\Traits;

/**
 * Trait caching generik untuk lookup model by ID yang sering dipanggil
 * berulang dengan ID yang sama di dalam loop (mengatasi N+1 query problem).
 */
trait CachesLookupModels
{
    /**
     * Cache per-model per-ID. Struktur: [ModelClass => [id => instance]]
     */
    protected array $lookupCache = [];

    /**
     * Cache untuk hasil non-keyed (misal ->first() atau hasil custom lain).
     */
    protected array $singleCache = [];

    /**
     * Ambil model by ID, cache di request ini. Query DB hanya jika
     * ID belum pernah di-fetch sebelumnya.
     */
    protected function cached(string $modelClass, $id, array $columns = ['*'])
    {
        if ($id === null) {
            return null;
        }

        if (array_key_exists($id, $this->lookupCache[$modelClass] ?? [])) {
            return $this->lookupCache[$modelClass][$id];
        }

        $result = $modelClass::select($columns)->find($id);
        $this->lookupCache[$modelClass][$id] = $result;

        return $result;
    }

    /**
     * Cache untuk hasil yang tidak di-key oleh ID (misal ->first()
     * atau hasil komputasi lain yang dipanggil berulang dengan
     * parameter yang sama persis di dalam 1 request).
     */
    protected function cachedSingle(string $key, \Closure $resolver)
    {
        if (array_key_exists($key, $this->singleCache)) {
            return $this->singleCache[$key];
        }

        return $this->singleCache[$key] = $resolver();
    }

    protected function clearLookupCache(): void
    {
        $this->lookupCache = [];
        $this->singleCache = [];
    }
}
