<?php

namespace App\Filament\Resources\ReturPenjualans\Pages;

use App\Filament\Resources\ReturPenjualans\ReturPenjualanResource;
use App\Models\DetailReturPenjualan;
use App\Models\Penjualan;
use App\Models\ReturPenjualan;
use App\Services\Akuntansi\ReturPenjualanSyncService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class FormReturPenjualan extends Page
{
    protected static string $resource = ReturPenjualanResource::class;

    protected string $view = 'filament.resources.retur-penjualans.pages.form-retur-penjualan';

    protected static ?string $title = 'Form Retur Penjualan';

    // ── URL STATE ───────────────────────────────────────────────────────────

    #[Url(as: 'cari', keep: false)]
    public string $searchNota = '';

    #[Url(as: 'nota', keep: false)]
    public ?int $selectedPenjualanId = null;

    #[Url(as: 'edit', keep: false)]
    public ?int $returId = null;

    // ── FORM STATE ──────────────────────────────────────────────────────────

    public ?ReturPenjualan $editingRetur = null;
    public ?Penjualan $selectedNota = null;

    /**
     * Item barang dari nota yang siap diretur.
     * Setiap item: detail_id, nama_barang, satuan, harga, hit_kbk, custom_m3,
     *   qty_beli, qty_teretur, sisa_qty, qty_retur, potongan, subtotal, selected
     */
    public array $items = [];

    public string $keterangan_retur = '';

    public string $tanggal = '';

    public bool $canSeePrice = false;

    // ── LIFECYCLE ───────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->canSeePrice = (bool) auth()->user()?->hasAnyRole(['edmeros', 'super_admin', 'Super Admin']);
        $this->tanggal = now()->format('Y-m-d');

        if ($this->returId) {
            $this->loadExistingRetur($this->returId);
        } elseif ($this->selectedPenjualanId) {
            $this->loadNota($this->selectedPenjualanId, silent: true);
        }
    }

    private function loadExistingRetur(int $id): void
    {
        $retur = ReturPenjualan::with(['penjualan.detail', 'details'])->find($id);
        if (!$retur || !$retur->penjualan) {
            Notification::make()->title('Data retur tidak ditemukan.')->danger()->send();
            return;
        }

        $this->editingRetur = $retur;
        $this->selectedPenjualanId = $retur->penjualan_id;
        $this->selectedNota = $retur->penjualan;
        $this->tanggal = $retur->tanggal ? $retur->tanggal->format('Y-m-d') : now()->format('Y-m-d');
        $this->keterangan_retur = $retur->keterangan ?? '';

        // Histori retur selain retur saat ini
        $historiRetur = DB::table('retur_penjualans')
            ->join('detail_retur_penjualans', 'retur_penjualans.id', '=', 'detail_retur_penjualans.retur_penjualan_id')
            ->where('retur_penjualans.no_nota', $retur->no_nota)
            ->where('retur_penjualans.id', '!=', $retur->id)
            ->select('detail_retur_penjualans.detail_penjualan_id', DB::raw('SUM(detail_retur_penjualans.qty) as total_retur'))
            ->groupBy('detail_retur_penjualans.detail_penjualan_id')
            ->pluck('total_retur', 'detail_penjualan_id')
            ->toArray();

        $detailReturSaatIni = $retur->details->keyBy('detail_penjualan_id');

        $this->items = [];
        foreach ($retur->penjualan->detail as $d) {
            $kategori = strtolower(trim($d->kategori_barang ?? ''));
            $namaLower = strtolower(trim($d->custom_nama ?: $d->nama_barang));
            $isVeneer = ($kategori === 'veneer' || str_contains($namaLower, 'veneer'));

            $qtyBeli = (float) ($d->jumlah ?? 0);
            $qtyTereturLain = (float) ($historiRetur[$d->id] ?? 0);
            $sisaQty = $isVeneer ? 0 : max(0, $qtyBeli - $qtyTereturLain);
            $harga = (float) ($d->harga ?? 0);

            $existingDetail = $detailReturSaatIni->get($d->id);
            $qtyRetur = $existingDetail ? (float) $existingDetail->qty : 0;
            $selected = ($qtyRetur > 0);

            $hitKbk = $existingDetail ? ($existingDetail->hit_kbk ?? $d->hit_kbk ?? 'b') : ($d->hit_kbk ?? 'b');
            $customM3 = (float) ($existingDetail ? ($existingDetail->custom_m3 ?? $d->custom_m3 ?? 0) : ($d->custom_m3 ?? 0));
            $potongan = (float) ($existingDetail ? ($existingDetail->potongan ?? $d->potongan ?? 0) : ($d->potongan ?? 0));

            $multiplier = ($hitKbk === 'm' && $customM3 > 0) ? $customM3 : $qtyRetur;
            $subtotal = $selected ? max(0, ($multiplier * $harga) - $potongan) : 0;

            $this->items[] = [
                'detail_id'    => $d->id,
                'nama_barang'  => $d->custom_nama ?: $d->nama_barang,
                'satuan'       => $d->satuan ?? 'pcs',
                'harga'        => $harga,
                'hit_kbk'      => $hitKbk,
                'custom_m3'    => $customM3,
                'qty_beli'     => $qtyBeli,
                'qty_teretur'  => $qtyTereturLain,
                'sisa_qty'     => $sisaQty,
                'qty_retur'    => $qtyRetur,
                'potongan'     => $potongan,
                'subtotal'     => $subtotal,
                'selected'     => $selected,
                'is_veneer'    => $isVeneer,
            ];
        }
    }

    // ── STEP 1: Daftar nota ─────────────────────────────────────────────────

    #[Computed]
    public function notaResults(): Collection
    {
        $notas = Penjualan::query()
            ->with(['detail'])
            ->when($this->searchNota, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('no_nota', 'like', "%{$this->searchNota}%")
                        ->orWhere('tujuan_nota', 'like', "%{$this->searchNota}%");
                });
            })
            ->orderByDesc('tanggal')
            ->limit(25)
            ->get();

        if ($notas->isEmpty()) {
            return collect();
        }

        $noNotas = $notas->pluck('no_nota')->toArray();

        // Ambil total qty yang sudah diretur per nota (dari data ERP sendiri)
        $returTotals = DB::table('retur_penjualans')
            ->join('detail_retur_penjualans', 'retur_penjualans.id', '=', 'detail_retur_penjualans.retur_penjualan_id')
            ->whereIn('retur_penjualans.no_nota', $noNotas)
            ->groupBy('retur_penjualans.no_nota')
            ->select('retur_penjualans.no_nota', DB::raw('SUM(detail_retur_penjualans.qty) as total_qty_retur'))
            ->pluck('total_qty_retur', 'no_nota')
            ->toArray();

        foreach ($notas as $nota) {
            $totalQtyBeli = (float) $nota->detail->sum('jumlah');
            $totalQtyRetur = (float) ($returTotals[$nota->no_nota] ?? 0);
            $nota->total_qty_beli = $totalQtyBeli;
            $nota->total_qty_retur = $totalQtyRetur;
            $nota->sisa_qty_retur = max(0, $totalQtyBeli - $totalQtyRetur);
            $nota->is_retur_habis = ($nota->sisa_qty_retur <= 0 && $totalQtyBeli > 0);
            $nota->pernah_diretur = ($totalQtyRetur > 0 && !$nota->is_retur_habis);
        }

        return $notas;
    }

    // ── STEP 2: Load & interaksi nota ───────────────────────────────────────

    public function pilihNota(int $id): void
    {
        $this->loadNota($id, silent: false);
    }

    private function loadNota(int $id, bool $silent = false): void
    {
        $nota = Penjualan::with(['detail'])->find($id);

        if (!$nota) {
            if (!$silent) {
                Notification::make()->title('Nota tidak ditemukan.')->danger()->send();
            }
            $this->resetSelection();
            return;
        }

        // Histori retur per detail_penjualan_id dari ERP
        $historiRetur = DB::table('retur_penjualans')
            ->join('detail_retur_penjualans', 'retur_penjualans.id', '=', 'detail_retur_penjualans.retur_penjualan_id')
            ->where('retur_penjualans.no_nota', $nota->no_nota)
            ->select('detail_retur_penjualans.detail_penjualan_id', DB::raw('SUM(detail_retur_penjualans.qty) as total_retur'))
            ->groupBy('detail_retur_penjualans.detail_penjualan_id')
            ->pluck('total_retur', 'detail_penjualan_id')
            ->toArray();

        $totalQtyBeli = (float) $nota->detail->sum('jumlah');
        $totalQtyTeretur = (float) array_sum($historiRetur);

        if ($totalQtyBeli > 0 && $totalQtyTeretur >= $totalQtyBeli) {
            if (!$silent) {
                Notification::make()
                    ->title('Semua Barang Sudah Diretur')
                    ->body("Semua barang pada nota {$nota->no_nota} sudah diretur seluruhnya.")
                    ->warning()
                    ->send();
            }
            $this->resetSelection();
            return;
        }

        $this->selectedPenjualanId = $nota->id;
        $this->selectedNota = $nota;
        $this->items = [];

        foreach ($nota->detail as $d) {
            // Validasi: hanya barang kategori plywood/triplek yang boleh diretur (veneer dilarang)
            $kategori = strtolower(trim($d->kategori_barang ?? ''));
            $namaLower = strtolower(trim($d->custom_nama ?: $d->nama_barang));
            $isVeneer = ($kategori === 'veneer' || str_contains($namaLower, 'veneer'));

            $qtyBeli = (float) ($d->jumlah ?? 0);
            $qtyTeretur = (float) ($historiRetur[$d->id] ?? 0);
            $sisaQty = $isVeneer ? 0 : max(0, $qtyBeli - $qtyTeretur);
            $harga = (float) ($d->harga ?? 0);

            $this->items[] = [
                'detail_id'    => $d->id,
                'nama_barang'  => $d->custom_nama ?: $d->nama_barang,
                'satuan'       => $d->satuan ?? 'pcs',
                'harga'        => $harga,
                'hit_kbk'      => $d->hit_kbk ?? 'b',
                'custom_m3'    => (float) ($d->custom_m3 ?? 0),
                'qty_beli'     => $qtyBeli,
                'qty_teretur'  => $qtyTeretur,
                'sisa_qty'     => $sisaQty,
                'qty_retur'    => 0,
                'potongan'     => (float) ($d->potongan ?? 0),
                'subtotal'     => 0,
                'selected'     => false,
                'is_veneer'    => $isVeneer,
            ];
        }
    }

    public function batalPilihNota(): void
    {
        $this->resetSelection();
    }

    private function resetSelection(): void
    {
        $this->editingRetur = null;
        $this->returId = null;
        $this->selectedPenjualanId = null;
        $this->selectedNota = null;
        $this->items = [];
        $this->keterangan_retur = '';
    }

    // ── INTERAKSI BARANG ────────────────────────────────────────────────────

    public function toggleItem(int $index): void
    {
        if (!isset($this->items[$index])) return;
        if (!empty($this->items[$index]['is_veneer'])) {
            Notification::make()
                ->title('Barang Tidak Bisa Diretur')
                ->body('Veneer tidak boleh diretur. Hanya Plywood / Triplek yang dapat diretur.')
                ->warning()
                ->send();
            return;
        }
        if (($this->items[$index]['sisa_qty'] ?? 0) <= 0) {
            $this->items[$index]['selected'] = false;
            $this->items[$index]['qty_retur'] = 0;
            return;
        }

        $current = $this->items[$index]['selected'];
        $this->items[$index]['selected'] = !$current;

        if ($this->items[$index]['selected']) {
            if ($this->items[$index]['qty_retur'] <= 0) {
                $this->items[$index]['qty_retur'] = $this->items[$index]['sisa_qty'];
            }
        } else {
            $this->items[$index]['qty_retur'] = 0;
        }

        $this->recalculateItemSubtotal($index);
    }

    public function toggleReturSemua(bool $setSemua = true): void
    {
        foreach ($this->items as $idx => $item) {
            if ($setSemua) {
                if (($item['sisa_qty'] ?? 0) > 0) {
                    $this->items[$idx]['selected'] = true;
                    $this->items[$idx]['qty_retur'] = $item['sisa_qty'];
                    $this->recalculateItemSubtotal($idx);
                }
            } else {
                $this->items[$idx]['selected'] = false;
                $this->items[$idx]['qty_retur'] = 0;
                $this->items[$idx]['subtotal'] = 0;
            }
        }
    }

    public function updatedItems($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) === 2 && $parts[1] === 'qty_retur') {
            $this->clampQtyLive((int) $parts[0]);
        }
    }

    private function clampQtyLive(int $idx): void
    {
        if (!isset($this->items[$idx])) return;

        $sisa = (float) ($this->items[$idx]['sisa_qty'] ?? 0);
        $raw  = $this->items[$idx]['qty_retur'];

        if ($raw === null || $raw === '') {
            $this->recalculateItemSubtotal($idx);
            return;
        }

        $qty = (float) $raw;

        if ($sisa > 0 && $qty > $sisa) {
            $qty = $sisa;
            $this->items[$idx]['qty_retur'] = $qty;
            Notification::make()->title("Maks. {$sisa} {$this->items[$idx]['satuan']}")->warning()->send();
        }

        if ($qty < 0) {
            $qty = 0;
            $this->items[$idx]['qty_retur'] = 0;
        }

        $this->recalculateItemSubtotal($idx);
    }

    public function finalizeQty(int $idx): void
    {
        if (!isset($this->items[$idx])) return;

        $sisa = (float) ($this->items[$idx]['sisa_qty'] ?? 0);
        $raw  = $this->items[$idx]['qty_retur'];
        $qty  = ($raw === null || $raw === '') ? 0 : (float) $raw;

        if ($sisa <= 0 || $qty < 1) {
            $this->items[$idx]['qty_retur'] = 0;
            $this->items[$idx]['selected']  = false;
            $this->items[$idx]['subtotal']  = 0;
            return;
        }

        if ($qty > $sisa) $qty = $sisa;
        $this->items[$idx]['qty_retur'] = $qty;
        $this->items[$idx]['selected']  = true;
        $this->recalculateItemSubtotal($idx);
    }

    private function recalculateItemSubtotal(int $idx): void
    {
        if (!isset($this->items[$idx])) return;

        $qty      = (float) ($this->items[$idx]['qty_retur'] ?? 0);
        $m3       = (float) ($this->items[$idx]['custom_m3'] ?? 0);
        $hitKbk   = $this->items[$idx]['hit_kbk'] ?? 'b';
        $harga    = (float) ($this->items[$idx]['harga'] ?? 0);
        $potongan = (float) ($this->items[$idx]['potongan'] ?? 0);

        $multiplier = ($hitKbk === 'm' && $m3 > 0) ? $m3 : $qty;
        $this->items[$idx]['subtotal'] = max(0, ($multiplier * $harga) - $potongan);
    }

    // ── COMPUTED ─────────────────────────────────────────────────────────────

    #[Computed]
    public function hasSisaBarang(): bool
    {
        return collect($this->items)->contains(fn($it) => ($it['sisa_qty'] ?? 0) > 0);
    }

    #[Computed]
    public function ringkasan(): array
    {
        $subTotal = 0.0;
        $jumlahItem = 0;

        foreach ($this->items as $item) {
            if (!($item['selected'] ?? false) || ($item['qty_retur'] ?? 0) <= 0) continue;
            $subTotal += (float) ($item['subtotal'] ?? 0);
            $jumlahItem++;
        }

        return [
            'sub_total'   => $subTotal,
            'total'       => $subTotal,
            'jumlah_item' => $jumlahItem,
        ];
    }

    // ── SIMPAN ───────────────────────────────────────────────────────────────

    public function simpanRetur(): void
    {
        if (!$this->selectedNota) {
            Notification::make()->title('Pilih nota penjualan terlebih dahulu.')->danger()->send();
            return;
        }

        // Finalize semua qty sebelum save
        foreach (array_keys($this->items) as $idx) {
            $this->finalizeQty($idx);
        }

        $itemsRetur = collect($this->items)
            ->filter(fn($it) => empty($it['is_veneer']) && ($it['selected'] ?? false) && ($it['qty_retur'] ?? 0) > 0);

        if ($itemsRetur->isEmpty()) {
            Notification::make()
                ->title('Pilih Barang')
                ->body('Pilih minimal 1 barang (hanya Plywood / Triplek) dengan qty retur lebih dari 0.')
                ->warning()->send();
            return;
        }

        $this->validate(
            ['keterangan_retur' => ['required', 'string', 'min:3']],
            ['keterangan_retur.required' => 'Alasan retur wajib diisi.', 'keterangan_retur.min' => 'Alasan retur terlalu singkat.']
        );

        $ringkasan = $this->ringkasan;

        try {
            $returRecord = null;

            DB::transaction(function () use ($itemsRetur, $ringkasan, &$returRecord) {
                $userId = auth()->id();

                if ($this->editingRetur) {
                    $returRecord = $this->editingRetur;
                    $returRecord->update([
                        'tanggal'               => $this->tanggal ?: today()->toDateString(),
                        'keterangan'            => $this->keterangan_retur,
                        'sub_total'             => $ringkasan['sub_total'],
                        'total'                 => $ringkasan['total'],
                        // Reset status sync jika retur diedit
                        'status_sync_akuntansi' => 'draft',
                    ]);

                    // Hapus detail lama untuk diperbarui
                    $returRecord->details()->delete();
                } else {
                    // Generate no_retur
                    $prefix    = 'RET-' . date('Ymd');
                    $lastCount = ReturPenjualan::where('no_retur', 'like', "{$prefix}%")->count() + 1;
                    $noRetur   = sprintf('%s-%04d', $prefix, $lastCount);

                    $returRecord = ReturPenjualan::create([
                        'tanggal'               => $this->tanggal ?: today()->toDateString(),
                        'no_retur'              => $noRetur,
                        'penjualan_id'          => $this->selectedNota->id,
                        'no_nota'               => $this->selectedNota->no_nota,
                        'tujuan_nota'           => $this->selectedNota->tujuan_nota,
                        'alamat'                => $this->selectedNota->alamat,
                        'metode_pembayaran'     => $this->selectedNota->metode_pembayaran ?? 'Tunai',
                        'kendaraan'             => $this->selectedNota->kendaraan,
                        'plat_kendaraan'        => $this->selectedNota->plat_kendaraan,
                        'nama_sopir'            => $this->selectedNota->nama_sopir,
                        'sub_total'             => $ringkasan['sub_total'],
                        'ppn_nominal'           => 0,
                        'total'                 => $ringkasan['total'],
                        'keterangan'            => $this->keterangan_retur,
                        'status_retur'          => 'draft',
                        'status_sync_akuntansi' => 'draft',
                        'dibuat_oleh'           => $userId,
                    ]);
                }

                foreach ($itemsRetur as $item) {
                    $qty      = (float) $item['qty_retur'];
                    $m3       = (float) ($item['custom_m3'] ?? 0);
                    $hitKbk   = $item['hit_kbk'] ?? 'b';
                    $harga    = (float) $item['harga'];
                    $potongan = (float) ($item['potongan'] ?? 0);
                    $multiplier = ($hitKbk === 'm' && $m3 > 0) ? $m3 : $qty;
                    $subtotal   = max(0, ($multiplier * $harga) - $potongan);

                    DetailReturPenjualan::create([
                        'retur_penjualan_id'  => $returRecord->id,
                        'detail_penjualan_id' => $item['detail_id'],
                        'nama_barang'         => $item['nama_barang'],
                        'satuan'              => $item['satuan'],
                        'qty'                 => $qty,
                        'custom_m3'           => $m3 > 0 ? $m3 : null,
                        'hit_kbk'             => $hitKbk,
                        'harga'               => $harga,
                        'potongan'            => $potongan,
                        'subtotal'            => $subtotal,
                        'keterangan'          => $this->keterangan_retur,
                    ]);
                }
            });

            Notification::make()
                ->title('Retur Berhasil Disimpan')
                ->body("No. Retur: {$returRecord->no_retur} — {$ringkasan['jumlah_item']} item. Silakan validasi untuk dikirim ke Akuntansi.")
                ->success()
                ->send();

            $this->redirect(ReturPenjualanResource::getUrl('index'));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Menyimpan')
                ->body('Terjadi kesalahan: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
