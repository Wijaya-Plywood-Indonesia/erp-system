<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\Select;

/**
 * Pencarian fleksibel untuk dropdown "Pilih Palet" di tombol create Modal
 * (menu produksi), polanya sama dengan pencarian "Catat Barang Keluar":
 *
 *  - Beberapa kata sekaligus, urutan bebas, semua kata harus cocok (AND).
 *      "sengon better 9mm" = "9mm better sengon"
 *  - Tebal:   "9m", "9mm", "t9"      -> tebal tepat 9 (tidak ikut 19 / 90)
 *  - Ukuran:  "122x244", "244x9", "122 x 244 x 9"
 *  - Palet:   "p12", "tj5", "pm3", "12" (angka polos = nomor palet / salah satu sisi ukuran)
 *  - Teks:    jenis kayu / jenis barang, kategori, grade / KW ("kw1"), asal ("hotpress")
 *  - Angka SISA di label TIDAK ikut dicari, jadi tidak bikin hasil nyasar.
 *
 * Cara pakai di Select:
 *
 *   PaletSearch::configure(
 *       Select::make('id_serah_terima_hp')->label('Pilih Palet'),
 *       fn (Get $get, ?ModalSanding $record) => self::getPaletEntries($get, $record),
 *   )
 *
 * Closure entries harus mengembalikan list array dengan kunci:
 *   id (wajib), label (wajib), identitas, no, kategori, jenis, grade, ukuran, asal, extra,
 *   pin (true = selalu paling atas, mis. "Afalan"), urut (angka; kecil -> besar, mis. tebal)
 * "ukuran" boleh string apa saja yang memuat 3 angka (p, l, t), mis. "122 x 244 x 9".
 */
class PaletSearch
{
    public static function configure(Select $select, Closure $entries, int $limit = 100, ?Closure $labelFallback = null): Select
    {
        // Cache per request supaya query berat tidak dijalankan 3x
        // (options, search, label) dalam satu request Livewire.
        $cache = new \stdClass;
        $cache->data = null;

        $resolve = function (Select $component) use ($entries, $cache): array {
            if ($cache->data === null) {
                $list = $component->evaluate($entries) ?? [];
                $cache->data = self::urutkan(array_values($list));
            }

            return $cache->data;
        };

        return $select
            ->options(function (Select $component) use ($resolve, $limit) {
                return self::keOpsi($resolve($component), $limit);
            })
            ->preload()
            ->searchable()
            ->optionsLimit($limit)
            ->getSearchResultsUsing(function (string $search, Select $component) use ($resolve, $limit) {
                $entries = $resolve($component);

                return self::keOpsi(self::cari($entries, $search), $limit);
            })
            ->getOptionLabelUsing(function ($value, Select $component) use ($resolve, $labelFallback) {
                foreach ($resolve($component) as $e) {
                    if ((string) $e['id'] === (string) $value) {
                        return $e['label'];
                    }
                }

                // Nilai terpilih tidak ada di daftar (mis. sisa sudah habis / kena filter):
                // pakai fallback kalau disediakan, kalau tidak Filament menampilkan nilainya.
                return $labelFallback ? $labelFallback($value) : null;
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    public static function cari(array $entries, string $search): array
    {
        $tokens = self::tokenize($search);

        if ($tokens === []) {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            fn (array $e) => self::cocok($e, $tokens),
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function tokenize(string $search): array
    {
        $q = mb_strtolower(trim($search));
        $q = str_replace(['×', '*'], 'x', $q);
        $q = str_replace(',', '.', $q);
        // "122 x 244 x 9" / "122 x244" -> "122x244x9"
        $q = preg_replace('/(\d)\s*x\s*(?=\d)/', '$1x', $q);
        // "kw 1" -> "kw1"
        $q = preg_replace('/\bkw\s+(\d)/', 'kw$1', $q);

        $tokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(fn ($t) => trim($t, '.'), $tokens),
            fn ($t) => $t !== '',
        ));
    }

    /**
     * @param  array<string, mixed>  $e
     * @param  array<int, string>  $tokens
     */
    protected static function cocok(array $e, array $tokens): bool
    {
        $dims = self::dimensi($e['ukuran'] ?? null);
        $dimStr = implode('x', $dims);

        $no = mb_strtolower(trim((string) ($e['no'] ?? '')));
        $identitas = mb_strtolower(trim((string) ($e['identitas'] ?? '')));

        $teks = mb_strtolower(implode(' ', array_filter([
            $identitas,
            $no !== '' ? 'p'.$no : null,
            $e['kategori'] ?? null,
            $e['jenis'] ?? null,
            $e['grade'] ?? null,
            $e['asal'] ?? null,
            $e['extra'] ?? null,
        ], fn ($v) => $v !== null && $v !== '' && $v !== '-')));

        $teksRapat = preg_replace('/[^a-z0-9.]/', '', $teks);

        foreach ($tokens as $t) {
            // 1) Tebal: 9m / 9mm / t9
            if (preg_match('/^(?:(\d+(?:\.\d+)?)(?:mm|m)|t(\d+(?:\.\d+)?))$/', $t, $m)) {
                $tebal = $m[1] !== '' ? $m[1] : ($m[2] ?? '');
                if (count($dims) === 3 && abs((float) $dims[2] - (float) $tebal) < 0.001) {
                    continue;
                }

                // "t9" bisa juga bagian dari teks (mis. kode) -> jatuh ke pencocokan teks
                if (! str_contains($teksRapat, $t)) {
                    return false;
                }

                continue;
            }

            // 2) Ukuran: 122x244, 244x9, 122x244x9 (batas awal harus di tepi angka)
            if (preg_match('/^\d+(?:\.\d+)?(?:x\d+(?:\.\d+)?)+$/', $t)) {
                if ($dimStr !== '' && preg_match('/(?:^|x)'.preg_quote($t, '/').'(?:$|x)/', $dimStr)) {
                    continue;
                }

                return false;
            }

            // 3) Angka polos: nomor palet atau salah satu sisi ukuran
            if (preg_match('/^\d+(?:\.\d+)?$/', $t)) {
                if (in_array($t, array_map('strval', $dims), true)) {
                    continue;
                }

                if ($no !== '' && str_starts_with($no, $t)) {
                    continue;
                }

                if (preg_match('/^[a-z]*'.preg_quote($t, '/').'/', $identitas)) {
                    continue;
                }

                return false;
            }

            // 4) Teks bebas (jenis, grade, kategori, asal, p12, tj5, kw1, ...)
            $rapat = preg_replace('/[^a-z0-9.]/', '', $t);
            if (str_contains($teks, $t) || ($rapat !== '' && str_contains($teksRapat, $rapat))) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Ambil [panjang, lebar, tebal] dari string/array apa pun yang memuat 3 angka.
     *
     * @return array<int, string>
     */
    protected static function dimensi(mixed $ukuran): array
    {
        if (is_array($ukuran)) {
            $angka = array_map(fn ($v) => (string) $v, $ukuran);
        } else {
            preg_match_all('/\d+(?:\.\d+)?/', (string) $ukuran, $m);
            $angka = $m[0] ?? [];
        }

        if (count($angka) < 3) {
            return [];
        }

        return array_map(
            fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'),
            array_slice($angka, 0, 3),
        );
    }

    /**
     * Palet terbaru di atas; entri khusus (mis. Afalan) bisa dipin dengan 'pin' => true.
     *
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int, array<string, mixed>>
     */
    protected static function urutkan(array $entries): array
    {
        usort($entries, function ($a, $b) {
            $pinA = ! empty($a['pin']) ? 1 : 0;
            $pinB = ! empty($b['pin']) ? 1 : 0;

            if ($pinA !== $pinB) {
                return $pinB <=> $pinA;
            }

            // Kalau kedua entri punya 'urut', pakai itu (kecil -> besar), mis. urut tebal.
            if (isset($a['urut'], $b['urut']) && $a['urut'] != $b['urut']) {
                return $a['urut'] <=> $b['urut'];
            }

            $idA = is_numeric($a['id']) ? abs((float) $a['id']) : 0;
            $idB = is_numeric($b['id']) ? abs((float) $b['id']) : 0;

            return $idB <=> $idA;
        });

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @return array<int|string, string>
     */
    protected static function keOpsi(array $entries, int $limit): array
    {
        $out = [];

        foreach (array_slice($entries, 0, $limit) as $e) {
            $out[$e['id']] = $e['label'];
        }

        return $out;
    }
}
