<?php

namespace App\Filament\Resources\VeneerMasuks\Pages;

use App\Filament\Resources\VeneerMasuks\VeneerMasukResource;
use App\Models\JenisKayu;
use App\Models\Ukuran;
use App\Models\HppVeneerBasahSummary;
use App\Models\StokVeneerKering;
use App\Models\VeneerMutasi;
use App\Models\VeneerMutasiDetail;
use App\Services\VeneerMutasiService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Computed;

class CreateVeneerMasuk extends Page
{
    protected static string $resource = VeneerMasukResource::class;

    public function getView(): string
    {
        return 'filament.resources.veneer-masuks.pages.create-veneer-masuk';
    }

    /* ─────────────── Header ─────────────── */
    public string $tanggal     = '';
    public string $no_nota     = '';
    public string $tujuan_nota = '';
    public string $keterangan  = '';

    /* ─────────────── Item input ─────────────── */
    public ?string $item_tipe_veneer   = null;
    public ?string $item_id_jenis_kayu = null;
    public ?string $item_kw            = null;
    public ?string $item_id_ukuran     = null;
    public ?int    $item_qty           = null;
    public int     $stok_sistem        = 0;

    /* ─────────────── Item list ─────────────── */
    public array $items = [];

    /* ─────────────── Non-veneer Item input ─────────────── */
    public string $nv_nama_barang = '';
    public ?int    $nv_jumlah      = null;
    public string $nv_satuan      = 'Pcs';
    public string $nv_keterangan  = '';

    /* ─────────────── Non-veneer Item list ─────────────── */
    public array $non_veneer_items = [];

    /* ══════════════════════════════════════════
     *  LIFECYCLE
     * ══════════════════════════════════════════ */

    public function mount(): void
    {
        $this->tanggal = now()->format('Y-m-d');

        if ($saved = session($this->sessionKey())) {
            $this->tanggal     = $saved['tanggal']     ?? $this->tanggal;
            $this->no_nota     = $saved['no_nota']     ?? '';
            $this->tujuan_nota = $saved['tujuan_nota'] ?? '';
            $this->keterangan  = $saved['keterangan']  ?? '';
            $this->items       = $saved['items']       ?? [];
            $this->non_veneer_items = $saved['non_veneer_items'] ?? [];
        }
    }

    public function updated(string $name): void
    {
        $this->syncSession();

        if ($name === 'item_tipe_veneer') {
            $this->item_id_jenis_kayu = null;
            $this->item_kw            = null;
            $this->item_id_ukuran     = null;
            $this->stok_sistem        = 0;
        }
        if ($name === 'item_id_jenis_kayu') {
            $this->item_kw        = null;
            $this->item_id_ukuran = null;
            $this->stok_sistem    = 0;
        }
        if ($name === 'item_kw') {
            $this->item_id_ukuran = null;
            $this->stok_sistem    = 0;
        }
        if ($name === 'item_id_ukuran') {
            $this->refreshStok();
        }
    }

    /* ══════════════════════════════════════════
     *  SESSION
     * ══════════════════════════════════════════ */

    private function sessionKey(): string
    {
        return 'vm_create_' . auth()->id();
    }

    private function syncSession(): void
    {
        session([$this->sessionKey() => [
            'tanggal'     => $this->tanggal,
            'no_nota'     => $this->no_nota,
            'tujuan_nota' => $this->tujuan_nota,
            'keterangan'  => $this->keterangan,
            'items'       => $this->items,
            'non_veneer_items' => $this->non_veneer_items,
        ]]);
    }

    /* ══════════════════════════════════════════
     *  COMPUTED OPTIONS
     * ══════════════════════════════════════════ */

    #[Computed]
    public function jenisKayuOptions(): array
    {
        if (!$this->item_tipe_veneer) return [];
        return JenisKayu::orderBy('nama_kayu')->pluck('nama_kayu', 'id')->toArray();
    }

    #[Computed]
    public function kwOptions(): array
    {
        // VM: tampilkan semua KW master — barang masuk bisa KW berapapun
        return [
            '1' => 'KW 1',
            '2' => 'KW 2',
            '3' => 'KW 3',
            '4' => 'KW 4',
        ];
    }

    #[Computed]
    public function ukuranOptions(): array
    {
        // VM: tampilkan semua ukuran master — barang masuk bisa ukuran berapapun
        return Ukuran::orderBy('panjang')->orderBy('lebar')->orderBy('tebal')
            ->get()->pluck('dimensi', 'id')->toArray();
    }

    /* ══════════════════════════════════════════
     *  STOK
     * ══════════════════════════════════════════ */

    private function refreshStok(): void
    {
        if (!$this->item_tipe_veneer || !$this->item_id_jenis_kayu
            || !$this->item_kw || !$this->item_id_ukuran) {
            $this->stok_sistem = 0;
            return;
        }

        $ukuran = Ukuran::find($this->item_id_ukuran);
        if (!$ukuran) return;

        if ($this->item_tipe_veneer === 'basah') {
            $s = HppVeneerBasahSummary::where([
                'id_jenis_kayu' => $this->item_id_jenis_kayu,
                'panjang' => $ukuran->panjang,
                'lebar'   => $ukuran->lebar,
                'tebal'   => $ukuran->tebal,
                'kw'      => $this->item_kw,
            ])->first();
            $this->stok_sistem = $s ? (int) $s->stok_lembar : 0;
        } else {
            $this->stok_sistem = StokVeneerKering::saldoLembarTerakhir(
                $this->item_id_ukuran, $this->item_id_jenis_kayu, $this->item_kw
            );
        }
    }

    /* ══════════════════════════════════════════
     *  ACTIONS
     * ══════════════════════════════════════════ */

    public function tambahBarang(): void
    {
        $this->validate([
            'item_tipe_veneer'   => 'required',
            'item_id_jenis_kayu' => 'required',
            'item_kw'            => 'required',
            'item_id_ukuran'     => 'required',
            'item_qty'           => 'required|integer|min:1',
        ]);

        $ukuran    = Ukuran::find($this->item_id_ukuran);
        $jenisKayu = JenisKayu::find($this->item_id_jenis_kayu);

        // Cari apakah kombinasi yang sama sudah ada di daftar
        $existingIndex = null;
        foreach ($this->items as $i => $item) {
            if (
                $item['tipe_veneer']   === $this->item_tipe_veneer   &&
                $item['id_jenis_kayu'] == $this->item_id_jenis_kayu  &&
                $item['kw']            === $this->item_kw             &&
                $item['id_ukuran']     == $this->item_id_ukuran
            ) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex !== null) {
            // Produk sama → cukup tambahkan qty
            $this->items[$existingIndex]['qty'] += $this->item_qty;
        } else {
            // Produk baru → tambah row baru
            $this->items[] = [
                'tipe_veneer'     => $this->item_tipe_veneer,
                'id_jenis_kayu'   => $this->item_id_jenis_kayu,
                'nama_jenis_kayu' => $jenisKayu?->nama_kayu ?? '-',
                'kw'              => $this->item_kw,
                'id_ukuran'       => $this->item_id_ukuran,
                'dimensi'         => $ukuran?->dimensi ?? '-',
                'qty'             => $this->item_qty,
                'stok_sebelum'    => $this->stok_sistem,
            ];
        }

        $this->item_tipe_veneer   = null;
        $this->item_id_jenis_kayu = null;
        $this->item_kw            = null;
        $this->item_id_ukuran     = null;
        $this->item_qty           = null;
        $this->stok_sistem        = 0;

        $this->syncSession();
    }


    public function hapusBarang(int $index): void
    {
        array_splice($this->items, $index, 1);
        $this->syncSession();
    }

    public function tambahBahanLain(): void
    {
        $this->validate([
            'nv_nama_barang' => 'required|string',
            'nv_jumlah'      => 'required|integer|min:1',
            'nv_satuan'      => 'required|string',
        ]);

        $this->non_veneer_items[] = [
            'nama_barang' => $this->nv_nama_barang,
            'jumlah'      => $this->nv_jumlah,
            'satuan'      => $this->nv_satuan,
            'keterangan'  => $this->nv_keterangan,
        ];

        $this->nv_nama_barang = '';
        $this->nv_jumlah      = null;
        $this->nv_satuan      = 'Pcs';
        $this->nv_keterangan  = '';

        $this->syncSession();
    }

    public function hapusBahanLain(int $index): void
    {
        array_splice($this->non_veneer_items, $index, 1);
        $this->syncSession();
    }

    public function simpanDraft(): void
    {
        $this->saveDocument('draft');
    }

    public function kirim(): void
    {
        $this->saveDocument('kirim');
    }

    private function saveDocument(string $status): void
    {
        $this->validate([
            'tanggal'     => 'required|date',
            'no_nota'     => 'required|string|unique:nota_barang_masuks,no_nota',
            'tujuan_nota' => 'required|string',
        ]);

        if (empty($this->items) && empty($this->non_veneer_items)) {
            Notification::make()->title('Tambahkan minimal 1 barang (veneer atau non-veneer).')->danger()->send();
            return;
        }

        $mutasi = VeneerMutasi::create([
            'tanggal'        => $this->tanggal,
            'tipe_transaksi' => 'masuk',
            'no_nota'        => $this->no_nota,
            'tujuan_nota'    => $this->tujuan_nota,
            'keterangan'     => $this->keterangan ?: null,
            'status'         => $status,
            'dibuat_oleh'    => auth()->id(),
        ]);

        foreach ($this->items as $item) {
            VeneerMutasiDetail::create([
                'id_veneer_mutasi' => $mutasi->id,
                'tipe_veneer'      => $item['tipe_veneer'],
                'id_jenis_kayu'    => $item['id_jenis_kayu'],
                'kw'               => $item['kw'],
                'id_ukuran'        => $item['id_ukuran'],
                'qty'              => (int) $item['qty'],
                'm3'               => 0,
            ]);
        }

        // Always process to generate NotaBarangMasuk and details (veneer items)
        app(VeneerMutasiService::class)->process($mutasi);

        // Save traditional non-veneer details directly to DetailNotaBarangMasuk table
        if ($mutasi->id_nota_bm) {
            \App\Models\DetailNotaBarangMasuk::where('id_nota_bm', $mutasi->id_nota_bm)
                ->where('nama_barang', 'not like', 'Veneer %')
                ->delete();

            foreach ($this->non_veneer_items as $item) {
                \App\Models\DetailNotaBarangMasuk::create([
                    'id_nota_bm'  => $mutasi->id_nota_bm,
                    'nama_barang' => $item['nama_barang'],
                    'jumlah'      => (int) $item['jumlah'],
                    'satuan'      => $item['satuan'] ?? 'Pcs',
                    'keterangan'  => $item['keterangan'] ?? null,
                ]);
            }
        }

        session()->forget($this->sessionKey());

        Notification::make()
            ->title($status === 'kirim' ? 'Berhasil dikirim & diposting ke BM.' : 'Draft berhasil disimpan.')
            ->success()->send();

        $this->redirect(url('/admin/nota-barang-masuks'));
    }
}
