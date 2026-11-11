{{-- resources/views/filament/pages/hpp-direksi-page.blade.php --}}
<x-filament-panels::page>

    @php
        $tahap = $this->tahap;
        $rp = fn ($v) => $v === null ? '—' : number_format($v, 0, ',', '.');
        $jt = fn ($v) => number_format($v / 1000000, 2, ',', '.').' jt';

        $bln = fn ($b) => \Carbon\Carbon::createFromFormat('Y-m-d', $b.'-01')->locale('id')->translatedFormat('M');
        $labelBulan = fn ($b) => \Carbon\Carbon::createFromFormat('Y-m-d', $b.'-01')->locale('id')->translatedFormat('F Y');

        // Asal kayu: Face/Back selalu dari kayu 260, Core selalu dari kayu 130
        $asalKayu = ['fb' => 'kayu 260', 'core' => 'kayu 130'];

        // Garis tren 6 bulan: ada tooltip per titik, garis putus-putus jika ada bulan tanpa data,
        // dan keterangan nilai awal → akhir di bawahnya.
        $spark = function (array $tren) use ($bln, $labelBulan, $jt) {
            $w = 150; $h = 36; $n = count($tren);

            $pts = [];
            foreach ($tren as $i => $t) {
                if ($t['total'] !== null) {
                    $pts[] = ['i' => $i, 'v' => $t['total'], 'b' => $t['bulan']];
                }
            }
            if (count($pts) < 2) {
                return ['svg' => '', 'caption' => ''];
            }

            $vals = array_column($pts, 'v');
            $min = min($vals); $max = max($vals);
            $rentang = ($max - $min) ?: 1;

            foreach ($pts as $k => $p) {
                $pts[$k]['x'] = round($n > 1 ? $p['i'] * ($w / ($n - 1)) : 0, 1);
                $pts[$k]['y'] = round($h - 5 - (($p['v'] - $min) / $rentang) * ($h - 10), 1);
            }

            $svg = '<svg viewBox="0 0 '.$w.' '.$h.'" width="'.$w.'" height="'.$h.'" style="overflow:visible">';
            for ($k = 1; $k < count($pts); $k++) {
                $a = $pts[$k - 1]; $b = $pts[$k];
                $putus = ($b['i'] - $a['i']) > 1; // ada bulan tanpa data di antaranya
                $svg .= '<line x1="'.$a['x'].'" y1="'.$a['y'].'" x2="'.$b['x'].'" y2="'.$b['y'].'" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round"'
                    .($putus ? ' stroke-dasharray="3 3" opacity=".6"' : '').'/>';
            }
            $last = count($pts) - 1;
            foreach ($pts as $k => $p) {
                $teks = $labelBulan($p['b']).': Rp '.number_format($p['v'], 0, ',', '.');
                $svg .= '<g><title>'.e($teks).'</title>'
                    .'<circle cx="'.$p['x'].'" cy="'.$p['y'].'" r="'.($k === $last ? 3.2 : 2).'" fill="#f59e0b"/>'
                    .'<circle cx="'.$p['x'].'" cy="'.$p['y'].'" r="7" fill="transparent"/></g>';
            }
            $svg .= '</svg>';

            $caption = $bln($pts[0]['b']).' '.$jt($pts[0]['v']).' → '.$bln($pts[$last]['b']).' '.$jt($pts[$last]['v']);

            return ['svg' => $svg, 'caption' => $caption];
        };
    @endphp

    {{-- Filter bulan --}}
    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 p-3 mb-5 flex items-center gap-3 flex-wrap">
        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400">Bulan:</span>
        <select wire:model.live="bulan"
            class="text-xs bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-sm px-3 py-1.5 outline-none focus:border-primary-500">
            @foreach($this->bulanList as $b)
                <option value="{{ $b }}">{{ $labelBulan($b) }}</option>
            @endforeach
        </select>
        <span class="ml-auto text-[10px] text-gray-400">
            Face/Back = tebal di bawah 1 mm (kayu 260) · Core = tebal 1 mm ke atas (kayu 130)
        </span>
    </div>

    @forelse($tahap as $kunci => $t)
        <div class="mb-6" wire:key="tahap-{{ $kunci }}-{{ $this->bulan }}">
            <div class="mb-2">
                <h2 class="text-sm font-black uppercase tracking-widest text-gray-800 dark:text-gray-100">{{ $t['judul'] }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t['deskripsi'] }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach($t['kartu'] as $kat => $k)
                    {{-- wire:key memuat bulan, jadi rincian selalu tertutup lagi saat bulan diganti --}}
                    <div class="bg-white dark:bg-gray-800 rounded-sm border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden"
                         x-data="{ detail: false }" wire:key="kartu-{{ $kunci }}-{{ $kat }}-{{ $this->bulan }}">

                        <div class="px-5 py-2.5 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 flex items-center justify-between">
                            <span class="text-[11px] font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">
                                {{ $k['label'] }}
                                <span class="ml-1 font-bold normal-case tracking-normal text-gray-400">· {{ $asalKayu[$kat] ?? '' }}</span>
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">
                                {{ $kat === 'fb' ? 'tebal < 1 mm' : 'tebal ≥ 1 mm' }}
                            </span>
                        </div>

                        <div class="px-5 py-4">
                            @if($k['total'] === null)
                                <div class="text-sm text-gray-400 py-4">Belum ada data masuk berHPP di bulan ini.</div>
                            @else
                                @php $sp = $spark($k['tren']); @endphp

                                <div class="flex items-end justify-between gap-4">
                                    <div>
                                        <div class="flex items-baseline gap-2">
                                            <span class="text-3xl font-black text-amber-600 dark:text-amber-400 tabular-nums">Rp {{ $rp($k['total']) }}</span>
                                            <span class="text-sm text-gray-400">per m³</span>
                                        </div>

                                        @if($k['selisih'] !== null)
                                            {{-- Biaya naik = merah (lebih mahal), turun = hijau (lebih murah) --}}
                                            <div @class(['mt-1 text-xs font-bold tabular-nums',
                                                'text-red-500 dark:text-red-400'         => $k['selisih'] > 0,
                                                'text-emerald-600 dark:text-emerald-400' => $k['selisih'] < 0,
                                                'text-gray-400'                          => $k['selisih'] == 0])>
                                                {{ $k['selisih'] > 0 ? '▲' : ($k['selisih'] < 0 ? '▼' : '•') }}
                                                Rp {{ number_format(abs($k['selisih']), 0, ',', '.') }}
                                                ({{ number_format(abs($k['selisih_pct']), 1, ',', '.') }}%)
                                                {{ $k['selisih'] > 0 ? 'lebih mahal' : ($k['selisih'] < 0 ? 'lebih murah' : 'sama') }}
                                                <span class="font-normal text-gray-400">dari bulan lalu</span>
                                            </div>
                                        @else
                                            <div class="mt-1 text-xs text-gray-400">Tidak ada pembanding bulan lalu.</div>
                                        @endif
                                    </div>

                                    @if($sp['svg'])
                                        <div class="text-right shrink-0">
                                            {!! $sp['svg'] !!}
                                            <div class="mt-0.5 text-[9px] tabular-nums text-gray-400">{{ $sp['caption'] }}</div>
                                            <div class="text-[9px] text-gray-300 dark:text-gray-600">arahkan kursor ke titik untuk melihat angkanya</div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 text-[11px] text-gray-500 dark:text-gray-400 tabular-nums">
                                    {{ number_format($k['m3'], 2, ',', '.') }} m³ masuk · {{ number_format($k['palet']) }} palet
                                    @if($k['estimasi_pct'] !== null && $k['estimasi_pct'] >= 0.5)
                                        <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-amber-100 dark:bg-amber-900/30 text-[9px] font-black uppercase text-amber-600 dark:text-amber-400">
                                            {{ number_format($k['estimasi_pct'], 0) }}% estimasi
                                        </span>
                                    @endif
                                </div>

                                {{-- Pembanding: HPP stok gudang (diabu-abukan jika stoknya terlalu sedikit) --}}
                                @php
                                    $stokSedikit = $k['stok_hpp'] !== null && $k['m3'] > 0 && $k['stok_m3'] < 0.1 * $k['m3'];
                                @endphp
                                <div @class(['mt-2 pt-2 border-t border-gray-100 dark:border-gray-700 text-[11px] tabular-nums',
                                    'text-gray-500 dark:text-gray-400' => ! $stokSedikit,
                                    'text-gray-400 dark:text-gray-500' => $stokSedikit])>
                                    Nilai stok gudang akhir bulan:
                                    @if($k['stok_hpp'] !== null)
                                        <b @class(['text-gray-700 dark:text-gray-200' => ! $stokSedikit])>Rp {{ $rp($k['stok_hpp']) }}</b> per m³
                                        <span class="text-gray-400">({{ number_format($k['stok_m3'], 2, ',', '.') }} m³ di gudang)</span>
                                        @if($stokSedikit)
                                            <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-gray-100 dark:bg-gray-700 text-[9px] font-black uppercase text-gray-500 dark:text-gray-300"
                                                  title="Stok di gudang jauh lebih kecil dari yang masuk bulan ini, sehingga angka ini kurang bermakna sebagai pembanding.">
                                                stok sedikit
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-gray-400">tidak ada stok</span>
                                    @endif
                                </div>

                                {{-- Rincian komponen (tersembunyi) --}}
                                <button type="button" @click="detail = !detail"
                                    class="mt-2 text-[10px] font-black uppercase tracking-wider text-gray-400 hover:text-amber-600">
                                    <span x-text="detail ? '▾ Sembunyikan rincian' : '▸ Lihat rincian komponen'"></span>
                                </button>
                                <div x-show="detail" x-cloak class="mt-2 space-y-1 text-xs tabular-nums">
                                    @foreach($k['rincian'] as $r)
                                        <div class="flex justify-between">
                                            <span class="text-gray-500 dark:text-gray-400">{{ $r['label'] }}</span>
                                            <span class="font-bold text-gray-700 dark:text-gray-200">Rp {{ $rp($r['nilai']) }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-sm text-gray-400 py-10 text-center">Belum ada data HPP.</div>
    @endforelse

    <div class="text-[11px] text-gray-500 dark:text-gray-400 leading-relaxed space-y-1">
        <div>
            <b>Cara membaca:</b> angka besar adalah <b>HPP produksi bulan itu</b>, yaitu rata-rata biaya veneer yang masuk pada bulan tersebut
            dibobot jumlah m³ (stok lama tidak ikut). "Nilai stok gudang" adalah rata-rata nilai seluruh veneer yang masih ada di gudang
            pada akhir bulan, sehingga bergerak lebih lambat.
        </div>
        <div>
            <b>Panah:</b> <span class="text-red-500">▲ merah</span> berarti biaya produksi naik (lebih mahal) dibanding bulan lalu,
            <span class="text-emerald-600">▼ hijau</span> berarti turun (lebih murah).
        </div>
        <div>
            <b>Garis 6 bulan:</b> menunjukkan arah, ujung kanan adalah bulan ini. Skala tiap garis menyesuaikan dirinya sendiri,
            jadi kemiringannya tidak bisa dibandingkan antarkartu. Lihat angka awal dan akhir di bawah garis atau arahkan kursor ke titiknya.
            Garis putus-putus berarti ada bulan tanpa data di antaranya.
        </div>
    </div>

</x-filament-panels::page>