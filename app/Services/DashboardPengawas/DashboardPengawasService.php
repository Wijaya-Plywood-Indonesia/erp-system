<?php

namespace App\Services\DashboardPengawas;

use App\Models\User;
use App\Services\DashboardPengawas\Sources\ConfigurableDashboardSource;
use App\Services\DashboardPengawas\Sources\GenericDashboardSource;
use App\Services\NewRekapAbsensiPegawaiService;
use App\Services\PotonganGajiService;
use Illuminate\Support\Collection;

class DashboardPengawasService
{
    /** @var DashboardSourceInterface[] */
    protected array $sources;

    public function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function getDataForUser(User $user, string $tanggal): Collection
    {
        $accessible = array_filter($this->sources, fn ($s) => $s->canAccess($user));

        // 1) Rekap absensi utuh, dipanggil SEKALI
        $rekap = $this->loadRekapUtuh($tanggal);

        // Dua index untuk remap
        $rekapById = $rekap->keyBy(fn ($r) => (string) ($r['id_pegawai'] ?? ''));
        $rekapByKode = $rekap->keyBy(fn ($r) => (string) ($r['kode_pegawai'] ?? ''));

        // 2) Remap ke tiap source
        $potonganService = app(PotonganGajiService::class);
        $data = collect();

        foreach ($accessible as $source) {
            $pegawai = $source->getPegawai($tanggal);

            if ($this->pakaiAbsensiSource($source)) {
                // Configurable / Generic: id pegawai langsung dari fetch() absensi source
                $ids = $source->getAbsensiSource()
                    ->fetch($tanggal)
                    ->pluck('id_pegawai')
                    ->filter()
                    ->unique()
                    ->values();

                $absen = $ids
                    ->map(fn ($id) => $rekapById->get((string) $id))
                    ->filter()
                    ->values();

                $pegawai['total'] = $ids->count();
            } else {
                // Source spesifik: daftar kode_pegawai dari tabel produksinya
                $absen = collect($pegawai['list'] ?? [])
                    ->map(fn ($kode) => $rekapByKode->get((string) $kode))
                    ->filter()
                    ->values();
            }

            // Potongan per source
            if ($absen->isNotEmpty() && method_exists($source, 'getPotonganMap')) {
                $potonganMap = $source->getPotonganMap($tanggal);
                $absen = $absen->map(function ($row) use ($potonganService, $potonganMap) {
                    $row['potongan'] = $potonganService->resolvePotongan(
                        $potonganMap,
                        $row['kode_pegawai'] ?? null
                    );

                    return $row;
                });
            }

            $data->push([
                'label' => $source->getLabel(),
                'produksi' => $source->getProduksi($tanggal),
                'serah_terima' => $source->getSerahTerima($tanggal),
                'pegawai' => $pegawai,
                'absen' => $absen,
            ]);
        }

        return $data;
    }

    /**
     * Source yang daftar pegawainya diambil dari fetch() absensi source.
     */
    protected function pakaiAbsensiSource(DashboardSourceInterface $source): bool
    {
        return ($source instanceof ConfigurableDashboardSource
                || $source instanceof GenericDashboardSource)
            && method_exists($source, 'getAbsensiSource')
            && $source->getAbsensiSource() !== null;
    }

    /**
     * Rekap absensi utuh (semua source), dipanggil 1x. Tanpa keyBy.
     */
    protected function loadRekapUtuh(string $tanggal): Collection
    {
        return app(NewRekapAbsensiPegawaiService::class)->getRekap($tanggal);
    }
}
