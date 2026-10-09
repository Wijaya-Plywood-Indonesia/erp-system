<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian fleksibel satu kotak untuk halaman Log.
 *
 * Aturan:
 *  - Dipecah per kata (spasi). SEMUA kata harus cocok (AND).
 *  - Dalam satu kata, ; atau | = ATAU. Koma di antara huruf juga ATAU ("A,B"),
 *    tetapi koma di antara angka dianggap desimal ("3,7" = 3.7).
 *  - Kata biasa  : dicari di keterangan, tipe, nama kayu/barang, dan KW.
 *                  Kata 1 huruf ("A") hanya dicari di KW supaya tidak cocok ke semua nama kayu.
 *  - Angka       : "130" = ukuran tepat 130 (panjang/lebar/tebal) atau KW yang mengandung angka itu.
 *  - Ukuran      : "130x65", "130 x 65", "130x65x3" = panjang x lebar x tebal (berurutan).
 *  - Tebal       : "3.7", "3,7", "3.7mm", "tebal 3.7"
 *  - KW          : "kw1", "kw 1", "grade A" -> hanya dicari di kolom KW; "KW"/"Grade" saja diabaikan.
 *  - Tanggal     : "12/03/2026", "12/03", "2026-03", "2026".
 *  - Huruf besar/kecil tidak berpengaruh.
 *
 * $config:
 *   text          : kolom teks di tabel log, mis. ['keterangan', 'tipe_transaksi']
 *   kw            : kolom KW/Grade, mis. 'kw_grade'
 *   relations     : kolom teks di relasi, mis. ['jenisKayu.nama_kayu']
 *   size          : kolom ukuran urut panjang, lebar, tebal, mis. ['panjang', 'lebar', 'tebal']
 *   size_relation : (opsional) nama relasi kalau kolom ukuran ada di tabel lain, mis. 'ukuran'
 *   date          : kolom tanggal, mis. 'tanggal'
 */
trait FlexibleLogSearch
{
    protected function flexibleSearch(Builder $query, string $input, array $config): Builder
    {
        // "3,7" -> "3.7" (koma desimal ala Indonesia), ".5" -> "0.5"
        $input = preg_replace('/(?<=\d),(?=\d)/', '.', $input);
        $input = preg_replace('/(?<![\d.])\.(?=\d)/', '0.', $input);

        // "130 x 65", "130*65", "130×65" -> "130x65"
        $input = preg_replace('/(?<=\d)\s*[x×*]\s*(?=\d)/iu', 'x', $input);

        $terms = preg_split('/\s+/u', trim($input), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $focus = null; // indeks kolom ukuran yang dituju oleh kata "tebal"/"panjang"/"lebar"

        foreach (array_slice($terms, 0, 8) as $term) {
            // satuan berdiri sendiri ("3.7 mm") diabaikan
            if (preg_match('/^(?:mm|cm|m)$/iu', $term)) {
                continue;
            }

            // "tebal" / "panjang" / "lebar" saja -> angka berikutnya hanya dicari di kolom itu
            $labels = $this->sizeLabels();
            if (isset($labels[mb_strtolower($term)])) {
                $focus = $labels[mb_strtolower($term)];
                continue;
            }

            // "tebal3.7", "tebal:3.7", "lebar=65"
            if (preg_match('/^(tebal|tbl|panjang|pjg|lebar|lbr)[:=]?(\d.*)$/iu', $term, $m)) {
                $focus = $labels[mb_strtolower($m[1])];
                $term = $m[2];
            }

            $alternatives = array_values(array_filter(
                array_map('trim', preg_split('/[,;|]+/u', $term) ?: []),
                fn ($a) => $a !== ''
            ));

            if (! $alternatives) {
                continue;
            }

            $query->where(function (Builder $group) use ($alternatives, $config, $focus) {
                foreach ($alternatives as $alt) {
                    $this->addSearchAlternative($group, $alt, $config, $focus);
                }
            });

            $focus = null;
        }

        return $query;
    }

    /** Kata yang menunjuk kolom ukuran (indeks mengikuti urutan config['size']: panjang, lebar, tebal). */
    protected function sizeLabels(): array
    {
        return [
            'panjang' => 0, 'pjg' => 0,
            'lebar'   => 1, 'lbr' => 1,
            'tebal'   => 2, 'tbl' => 2,
        ];
    }

    protected function addSearchAlternative(Builder $group, string $alt, array $config, ?int $focus = null): void
    {
        // "kw" / "grade" saja tidak berarti apa-apa
        if (preg_match('/^(?:kw|grade)$/iu', $alt)) {
            return;
        }

        // "kw1", "kw-1", "gradeA" -> khusus kolom KW
        if (preg_match('/^(?:kw|grade)[\s\-:.]*(.+)$/iu', $alt, $m)) {
            $this->orWhereKwLike($group, $config, $m[1]);
            return;
        }

        // Ukuran: 130x65 atau 130x65x3
        if (preg_match('/^\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?){1,2}$/iu', $alt)) {
            $this->orWhereSize($group, $config, array_map('floatval', explode('x', strtolower($alt))), true);
            return;
        }

        // Angka: 130, 3.7, 130cm, 3.7mm
        if (preg_match('/^(\d+(?:\.\d+)?)(?:cm|mm)?$/iu', $alt, $m)) {
            // Kalau didahului "tebal"/"panjang"/"lebar": hanya cari di kolom itu
            if ($focus !== null) {
                $this->orWhereSize($group, $config, [(float) $m[1]], false, $focus);
                return;
            }

            $this->orWhereKwLike($group, $config, $m[1]);
            $this->orWhereSize($group, $config, [(float) $m[1]], false);

            if (preg_match('/^\d{4}$/', $m[1])) {
                $this->orWhereDateLike($group, $config, $m[1]); // tahun
            }
            return;
        }

        // Tanggal: 12/03/2026, 12-03, 2026-03
        if (preg_match('#^\d{1,4}[/-]\d{1,4}(?:[/-]\d{1,4})?$#', $alt)) {
            $this->orWhereDateLike($group, $config, $alt);
            return;
        }

        // Kata biasa: 1 huruf (mis. "A") hanya KW agar tidak cocok ke semua nama kayu
        $this->orWhereKwLike($group, $config, $alt);

        if (mb_strlen($alt) < 2) {
            return;
        }

        $like = $this->searchLike($alt);

        foreach ($config['text'] ?? [] as $col) {
            $group->orWhere($group->getModel()->qualifyColumn($col), 'like', $like);
        }

        foreach ($config['relations'] ?? [] as $path) {
            $parts = explode('.', $path);
            $field = array_pop($parts);

            $group->orWhereHas(implode('.', $parts), function (Builder $r) use ($field, $like) {
                $r->where($r->getModel()->qualifyColumn($field), 'like', $like);
            });
        }
    }

    /** KW tidak peka huruf besar/kecil dan spasi diabaikan ("KW 1" = "KW1"). */
    protected function orWhereKwLike(Builder $group, array $config, string $term): void
    {
        if (empty($config['kw'])) {
            return;
        }

        $term = preg_replace('/\s+/u', '', $term);
        if ($term === '') {
            return;
        }

        $wrapped = $group->getQuery()->getGrammar()->wrap($group->getModel()->qualifyColumn($config['kw']));

        $group->orWhereRaw("REPLACE(UPPER({$wrapped}), ' ', '') LIKE ?", [$this->searchLike(mb_strtoupper($term))]);
    }

    /**
     * $ordered = true  : angka dicocokkan berurutan ke panjang, lebar, tebal (AND)
     * $ordered = false : angka dicocokkan ke salah satu kolom ukuran (OR)
     * $only            : indeks kolom ukuran tunggal (0 panjang, 1 lebar, 2 tebal)
     */
    protected function orWhereSize(Builder $group, array $config, array $numbers, bool $ordered, ?int $only = null): void
    {
        $cols = $config['size'] ?? [];
        if ($only !== null) {
            $cols = isset($cols[$only]) ? [$cols[$only]] : [];
        }
        if (! $cols) {
            return;
        }

        $apply = function (Builder $b) use ($cols, $numbers, $ordered) {
            $grammar = $b->getQuery()->getGrammar();

            if ($ordered) {
                foreach ($numbers as $i => $n) {
                    if (! isset($cols[$i])) {
                        break;
                    }
                    $wrapped = $grammar->wrap($b->getModel()->qualifyColumn($cols[$i]));
                    $b->whereRaw("ABS({$wrapped} - ?) < 0.0001", [$n]);
                }
                return;
            }

            $b->where(function (Builder $w) use ($cols, $numbers, $grammar, $b) {
                foreach ($cols as $col) {
                    $wrapped = $grammar->wrap($b->getModel()->qualifyColumn($col));
                    $w->orWhereRaw("ABS({$wrapped} - ?) < 0.0001", [$numbers[0]]);
                }
            });
        };

        if (! empty($config['size_relation'])) {
            $group->orWhereHas($config['size_relation'], $apply);
        } else {
            $group->orWhere($apply);
        }
    }

    protected function orWhereDateLike(Builder $group, array $config, string $term): void
    {
        if (empty($config['date'])) {
            return;
        }

        $wrapped = $group->getQuery()->getGrammar()->wrap($group->getModel()->qualifyColumn($config['date']));
        $like = $this->searchLike($term);

        $group->orWhereRaw("DATE_FORMAT({$wrapped}, '%d/%m/%Y') LIKE ?", [$like]);
        $group->orWhereRaw("DATE_FORMAT({$wrapped}, '%Y-%m-%d') LIKE ?", [$like]);
    }

    protected function searchLike(string $value): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
    }
}