<?php

namespace App\Filament\Pages;

use App\Models\GudangVeneerKering as GudangVeneerKeringModel;
use App\Models\StokVeneerKering;
use App\Models\Ukuran;
use App\Models\VeneerKeringMutasiKeluar;
use App\Models\VeneerKeringMutasiKeluarPalet;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\SerahTerimaVeneerKeringService;
use App\Services\VeneerMutasiService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class GudangVeneerKering extends Page
{
    use HasPageShield;

    protected string $view = 'filament.pages.gudang-veneer-kering';

    protected static ?string $navigationLabel = 'Gudang Veneer Kering';
    protected static string|UnitEnum|null $navigationGroup = 'Gudang';
    protected static ?string $title          = 'Gudang Veneer Kering';
    protected static ?int    $navigationSort = 21;

    // Tab aktif: 'masuk' (Serah Terima) / 'keluar' (Veneer Keluar)
    public string $activeTab = 'masuk';

    // Search bebas: cocok ke ukuran (p x l x t), KW, atau nama jenis kayu
    public string $search = '';

    // Search riwayat keluar
    public string $keluarSearchQuery = '';

    // ── Form Barang Keluar ──
    public bool $showFormKeluarModal = false;
    public ?int $selectedStokId = null;          // id baris summary (StokVeneerKering latest per kombinasi)
    public $jumlahPalet = 1;
    public array $paletQuantities = [0 => ''];
    public string $tujuanKeluar = 'Repair';
    public string $keteranganKeluar = '';

    protected $queryString = ['activeTab'];

    /**
     * Ringkasan stok per kombinasi produk (id_ukuran + id_jenis_kayu + kw).
     * Logika IDENTIK dgn halaman Stok Veneer Kering (MAX(id), stok_m3_sesudah > 0,
     * total_lembar = SUM(masuk) - SUM(keluar)).
     */
    public function getSummariesProperty()
    {
        $rows = StokVeneerKering::query()
            ->with(['ukuran', 'jenisKayu'])
            ->select('stok_veneer_kerings.*')
            ->join(
                DB::raw('(SELECT MAX(id) as max_id FROM stok_veneer_kerings GROUP BY id_ukuran, id_jenis_kayu, kw) as latest'),
                fn ($join) => $join->on('stok_veneer_kerings.id', '=', 'latest.max_id')
            )
            ->where('stok_m3_sesudah', '>', 0)
            ->get();

        $rows = $rows->map(function (StokVeneerKering $row) {
            $row->total_lembar = StokVeneerKering::saldoLembarTerakhir(
                (int) $row->id_ukuran,
                (int) $row->id_jenis_kayu,
                (string) $row->kw
            );

            return $row;
        });

        if (trim($this->search) !== '') {
            $needle = strtolower(trim($this->search));

            $rows = $rows->filter(function (StokVeneerKering $row) use ($needle) {
                $dimensi = strtolower(
                    rtrim(rtrim(number_format((float) $row->ukuran?->panjang, 2), '0'), '.') . 'x' .
                    rtrim(rtrim(number_format((float) $row->ukuran?->lebar, 2), '0'), '.') . 'x' .
                    rtrim(rtrim(number_format((float) $row->ukuran?->tebal, 2), '0'), '.')
                );
                $kw   = strtolower((string) $row->kw);
                $kayu = strtolower((string) $row->jenisKayu?->nama_kayu);

                return str_contains($dimensi, $needle)
                    || str_contains($kw, $needle)
                    || str_contains($kayu, $needle);
            });
        }

        return $rows;
    }

    public function getFacebackProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) <= 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    public function getCoreProperty()
    {
        return $this->summaries
            ->filter(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0) > 1)
            ->sortBy(fn (StokVeneerKering $r) => (float) ($r->ukuran?->tebal ?? 0))
            ->values();
    }

    // ─── SERAH TERIMA ─────────────────────────────────────────────────────────

    public function getSerahTerimaProperty()
    {
        $keringLike = SerahTerimaVeneerKeringService::TIPE_KERING_LIKE;

        $rows = VeneerMutasi::query()
            ->whereNotNull('id_nota_bm')
            ->whereHas('notaBm', fn ($q) => $q->whereNotNull('divalidasi_oleh'))
            ->whereHas('details', fn ($q) => $q->where('tipe_veneer', 'like', $keringLike))
            ->with([
                'details' => fn ($q) => $q
                    ->where('tipe_veneer', 'like', $keringLike)
                    ->with(['ukuran', 'jenisKayu', 'stokVeneerKering']),
                'notaBm.detail',
            ])
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        foreach ($rows as $vm) {
            $kandidat = $vm->notaBm?->detail ?? collect();

            foreach ($vm->details as $d) {
                $d->keterangan_nota = $this->cocokkanKeterangan($d, $kandidat);
            }
        }

        return $rows
            ->sortBy(fn (VeneerMutasi $vm) => $vm->details->every(fn ($d) => $d->stokVeneerKering !== null) ? 1 : 0)
            ->values();
    }

    protected function cocokkanKeterangan(VeneerMutasiDetail $detail, Collection $kandidat): ?string
    {
        $qty  = (int) round((float) $detail->qty);
        $kw   = strtolower(trim((string) $detail->kw));
        $kayu = strtolower(trim((string) ($detail->jenisKayu?->nama_kayu ?? '')));

        $match = $kandidat->first(function ($nd) use ($qty, $kw, $kayu) {
            $nama    = strtolower((string) ($nd->nama_barang ?? ''));
            $namaTNS = str_replace(' ', '', $nama);
            $jml     = (int) round((float) ($nd->jumlah ?? 0));

            $cocokQty  = $qty > 0 && $jml === $qty;
            $cocokKayu = $kayu !== '' && str_contains($nama, $kayu);
            $cocokKw   = $kw !== '' && (
                str_contains($nama, 'kw ' . $kw) ||
                str_contains($namaTNS, 'kw' . $kw)
            );

            return $cocokQty && $cocokKayu && $cocokKw;
        });

        $ket = $match?->keterangan;

        return ($ket !== null && trim((string) $ket) !== '') ? (string) $ket : null;
    }

    public function terima(int $id, array $detailIds = []): void
    {
        $keringLike = SerahTerimaVeneerKeringService::TIPE_KERING_LIKE;

        $mutasi = VeneerMutasi::query()
            ->with([
                'notaBm',
                'details' => fn ($q) => $q->where('tipe_veneer', 'like', $keringLike),
            ])
            ->findOrFail($id);

        if (! $mutasi->id_nota_bm || ! $mutasi->notaBm || ! $mutasi->notaBm->divalidasi_oleh) {
            Notification::make()->title('Nota belum divalidasi.')->danger()->send();
            return;
        }

        // Normalisasi pilihan: hanya id detail milik mutasi ini.
        $detailIds = array_values(array_intersect(
            array_map('intval', $detailIds),
            $mutasi->details->pluck('id')->all()
        ));

        if (empty($detailIds)) {
            Notification::make()->title('Pilih minimal satu barang untuk diterima.')->warning()->send();
            return;
        }

        // Buang yang sudah pernah diterima (per-detail).
        $sudahIds = StokVeneerKering::query()
            ->whereIn('id_veneer_mutasi_detail', $detailIds)
            ->pluck('id_veneer_mutasi_detail')
            ->all();

        $detailIds = array_values(array_diff($detailIds, $sudahIds));

        if (empty($detailIds)) {
            Notification::make()->title('Barang terpilih sudah diterima semua.')->warning()->send();
            return;
        }

        app(SerahTerimaVeneerKeringService::class)->terima($mutasi, (int) auth()->id(), $detailIds);

        Notification::make()
            ->title(count($detailIds) . ' barang berhasil diterima ke Gudang Veneer Kering.')
            ->success()
            ->send();
    }

    // ─── VENEER KELUAR ────────────────────────────────────────────────────────

    /**
     * Sinkronkan jumlah field "isi per palet" saat angka Jumlah Palet berubah.
     */
    public function updatedJumlahPalet($value): void
    {
        if ($value === '' || $value === null || $value === 0 || $value === '0') {
            return; // biarkan saat user sedang mengosongkan input
        }

        $count = max(1, intval($value));
        $this->paletQuantities = array_slice($this->paletQuantities, 0, $count);

        while (count($this->paletQuantities) < $count) {
            $this->paletQuantities[] = '';
        }
    }

    public function hapusPalet(int $index): void
    {
        if (isset($this->paletQuantities[$index])) {
            unset($this->paletQuantities[$index]);
            $this->paletQuantities = array_values($this->paletQuantities);
            $this->jumlahPalet = count($this->paletQuantities);
        }
    }

    /**
     * Proses pengeluaran veneer kering:
     * 1. Validasi stok cukup (saldo lembar per kombinasi).
     * 2. Simpan header VeneerKeringMutasiKeluar + rincian palet.
     * 3. Catat baris KELUAR di stok_veneer_kerings + recalc, sehingga
     *    Stok & Log HPP langsung terpotong.
     */
    public function prosesKeluar(): void
    {
        // Tujuan keluar dikunci: selalu Repair.
        $this->tujuanKeluar = 'Repair';

        $totalLembar = array_sum(array_map('intval', $this->paletQuantities));

        if (! $this->selectedStokId || $totalLembar <= 0 || trim($this->tujuanKeluar) === '') {
            Notification::make()
                ->danger()
                ->title('Input Gagal')
                ->body('Pilih stok, isi kuantitas palet, dan tujuan keluar wajib diisi.')
                ->send();
            return;
        }

        try {
            DB::transaction(function () use ($totalLembar) {
                // Baris summary yang dipilih = wakil kombinasi produk.
                $ref = StokVeneerKering::with('ukuran')->findOrFail($this->selectedStokId);

                $idUkuran    = (int) $ref->id_ukuran;
                $idJenisKayu = (int) $ref->id_jenis_kayu;
                $kw          = (string) $ref->kw;

                $saldo = StokVeneerKering::saldoLembarTerakhir($idUkuran, $idJenisKayu, $kw);
                if ($totalLembar > $saldo) {
                    throw new \Exception("Stok tidak mencukupi. Tersedia: {$saldo} lembar.");
                }

                $ukuran = $ref->ukuran ?? Ukuran::findOrFail($idUkuran);
                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $totalLembar) / 10000000;

                $user     = Auth::user();
                $userName = $user?->name ?? 'System';

                // 1. Header mutasi keluar
                $mutasi = VeneerKeringMutasiKeluar::create([
                    'id_ukuran'        => $idUkuran,
                    'id_jenis_kayu'    => $idJenisKayu,
                    'kw'               => $kw,
                    'jumlah_palet'     => count($this->paletQuantities),
                    'qty'              => $totalLembar,
                    'm3'               => $m3,
                    'tujuan_keluar'    => trim($this->tujuanKeluar),
                    'dikeluarkan_oleh' => $user?->id,
                    'keterangan'       => trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : null,
                ]);

                // 2. Rincian palet + 3. baris KELUAR di ledger PER PALET,
                //    supaya log menampilkan tiap palet sebagai transaksi sendiri
                //    (contoh: P1 -20 (50->30), lalu P2 -29 (30->1)).
                $ketDasar = 'Keluar ke [' . trim($this->tujuanKeluar) . ']'
                    . ' | Oleh: ' . $userName
                    . ' | Ket: ' . (trim($this->keteranganKeluar) !== '' ? trim($this->keteranganKeluar) : '-');

                $totalPalet = count($this->paletQuantities);

                foreach ($this->paletQuantities as $index => $qty) {
                    $qtyPalet = intval($qty);

                    VeneerKeringMutasiKeluarPalet::create([
                        'id_mutasi_keluar' => $mutasi->id,
                        'no_palet'         => $index + 1,
                        'qty'              => $qtyPalet,
                    ]);

                    if ($qtyPalet <= 0) {
                        continue; // palet kosong tidak perlu baris ledger
                    }

                    $m3Palet = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $qtyPalet) / 10000000;

                    StokVeneerKering::create([
                        'id_produksi_dryer'       => null,
                        'id_ukuran'               => $idUkuran,
                        'id_jenis_kayu'           => $idJenisKayu,
                        'kw'                      => $kw,
                        'jenis_transaksi'         => 'keluar',
                        'tanggal_transaksi'       => now()->toDateString(),
                        'qty'                     => $qtyPalet,
                        'm3'                      => $m3Palet,
                        'hpp_veneer_basah_per_m3' => 0,
                        'ongkos_dryer_per_m3'     => 0,
                        'hpp_kering_per_m3'       => 0,
                        'nilai_transaksi'         => 0,
                        'stok_lembar_sebelum'     => 0,
                        'stok_lembar_sesudah'     => 0,
                        'stok_m3_sebelum'         => 0,
                        'stok_m3_sesudah'         => 0,
                        'nilai_stok_sebelum'      => 0,
                        'nilai_stok_sesudah'      => 0,
                        'hpp_average'             => 0,
                        'keterangan'              => 'Palet ' . ($index + 1) . '/' . $totalPalet . ' | ' . $ketDasar,
                    ]);
                }

                // Recalc sekali di akhir: snapshot sebelum/sesudah tiap baris
                // (termasuk antar-palet) terhitung berantai dengan benar.
                app(VeneerMutasiService::class)->recalculateStokKering($idUkuran, $idJenisKayu, $kw);
            });

            // Reset form
            $this->selectedStokId      = null;
            $this->jumlahPalet         = 1;
            $this->paletQuantities     = [0 => ''];
            $this->tujuanKeluar        = 'Repair';
            $this->keteranganKeluar    = '';
            $this->showFormKeluarModal = false;

            Notification::make()
                ->success()
                ->title('Mutasi Keluar Berhasil')
                ->body("{$totalLembar} lembar veneer kering berhasil dikeluarkan dari stok.")
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Gagal Mengeluarkan Barang')
                ->body($e->getMessage())
                ->send();
        }
    }

    /**
     * Riwayat mutasi keluar (terbaru dulu), dengan pencarian bebas.
     */
    public function getRiwayatKeluarProperty(): Collection
    {
        $query = VeneerKeringMutasiKeluar::with(['ukuran', 'jenisKayu', 'palets', 'operator'])
            ->orderByDesc('created_at');

        if (trim($this->keluarSearchQuery) !== '') {
            $q = strtolower(trim($this->keluarSearchQuery));
            $query->where(function ($query) use ($q) {
                $query->whereHas('jenisKayu', fn ($qr) => $qr->whereRaw('LOWER(nama_kayu) LIKE ?', ["%{$q}%"]))
                    ->orWhereRaw('LOWER(kw) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(tujuan_keluar) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(keterangan) LIKE ?', ["%{$q}%"]);
            });
        }

        return $query->get();
    }
}