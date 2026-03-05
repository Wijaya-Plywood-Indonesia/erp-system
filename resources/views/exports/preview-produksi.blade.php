<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Persentase Kayu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; }
            .p-6 { padding: 0; }
        }
    </style>
</head>
<body class="antialiased text-slate-800 font-bold">
    <div class="p-6">
        <div class="mb-6 bg-white p-4 rounded-lg border border-slate-900 shadow-sm no-print">
            <form action="{{ url()->current() }}" method="GET">
                <div class="flex items-center justify-between  gap-4">
                    {{-- <div class="text-3xl font-bold text-slate-900">
                        PREVIEW EXPORT EXCEL
                    </div> --}}
                    <div class="flex items-end gap-4">

                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Bulan Produksi</label>
                            <select name="bulan" class="border border-slate-900 rounded px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach(range(1, 12) as $m)
                                    <option value="{{ sprintf('%02d', $m) }}" {{ $selectedBulan == $m ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-700 uppercase mb-1">Tahun</label>
                            <select name="tahun" class="border border-slate-900 rounded px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-blue-500 outline-none">
                                @foreach(range(date('Y')-2, date('Y')) as $y)
                                    <option value="{{ $y }}" {{ $selectedTahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="bg-slate-900 text-white px-6 py-2 rounded text-sm font-black hover:bg-slate-800 transition-colors">
                            TAMPILKAN DATA
                        </button>
                    </div>
                    @php
                        // Validasi apakah sheets kosong atau null
                        $isSheetsEmpty = empty($sheets) || count($sheets) === 0;
                    @endphp

                    @if($isSheetsEmpty)
                        {{-- Tampilan saat DISABLE --}}
                        <button type="button" 
                                onclick="alert('Maaf, data kosong. Tidak ada laporan yang bisa di-export untuk periode ini.')"
                                class="inline-flex items-center px-4 py-2 bg-slate-400 cursor-not-allowed text-white text-sm font-bold rounded-lg shadow-sm">
                            
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            
                            EXPORT EXCEL (KOSONG)
                        </button>
                    @else
                        {{-- Tampilan saat AKTIF --}}
                        <a href="{{ route('produksi.export-excel', request()->query()) }}" 
                        target="_blank"
                        class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-sm transition-all">
                            
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            
                            EXPORT EXCEL
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-lg shadow-sm border border-slate-900">
            <table class="w-full border-collapse bg-white text-sm font-sans">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-900 text-slate-900 uppercase">
                        <th colspan="20" class="py-4 text-center font-black text-lg">
                            KAYU {{ $activeSheet ?? 'KOSONG'}}
                        </th>
                    </tr>
                    <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-900">
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-24 font-bold">Tanggal</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 font-bold">Habis</th>
                        <th colspan="5" class="border-r border-slate-900 px-3 py-2 font-bold uppercase">Kayu</th>
                        <th colspan="5" rowspan="2" class="border-r border-slate-900 p-0 w-[352px]">
                            <table class="w-full table-fixed border-collapse">
                                <thead>
                                    <tr>
                                        <th colspan="5" class="border-b border-slate-900 py-2 text-center uppercase tracking-wider font-bold">Veneer</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="5" class="grid w-[352px] grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px] font-bold">
                                            <div class="text-center flex items-center justify-center min-w-16 h-full font-black">P</div>
                                            <div class="text-center flex items-center justify-center min-w-16 h-full font-black">L</div>
                                            <div class="text-center flex items-center justify-center min-w-12 h-full font-black">T</div>
                                            <div class="text-center font-mono flex items-center justify-center min-w-20 h-full font-black">TOTAL</div>
                                            <div class="bg-emerald-50/20 text-right pr-2 font-black h-full min-w-24 flex items-center justify-end">M³</div>
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-32 font-bold">Jam Kerja</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 font-black">%</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-emerald-100/50 text-emerald-800 font-black uppercase">Harga Veneer / m³</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 w-24 text-center p-0 font-bold">Pekerja</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-amber-50/50 text-amber-800 w-32 text-center p-0 font-bold">Ongkos / pkj</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-orange-100/40 text-orange-900 font-black uppercase">Harga V + Ongkos</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-800 w-32 text-center p-0 font-bold">Penyusutan</th>
                        <th rowspan="2" class="px-3 py-2 bg-yellow-100/40 text-yellow-900 font-black uppercase">Harga VOP</th>
                    </tr>
                    <tr class="bg-slate-50 border-b border-slate-900 text-slate-900 uppercase">
                        <th class="border-r border-slate-900 px-2 py-1 font-bold">Lahan</th>
                        <th class="border-r border-slate-900 px-2 py-1 font-bold">Batang</th>
                        <th class="border-r border-slate-900 px-2 py-1 font-bold">Pecah</th>
                        <th class="border-r border-slate-900 px-2 py-1 bg-orange-50/30 font-bold text-orange-900">m³</th>
                        <th class="border-r border-slate-900 px-2 py-1 bg-yellow-50/30 font-bold text-yellow-900">Poin</th>
                    </tr>

                    {{-- ! TOTALSSSS --}}
                    {{-- <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-900">
                        <th colspan="2" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">Total</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 text-slate-900 text-center font-bold"></th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">TBatang</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">TPecah</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">M3</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">poin</th>
                        
                            <th colspan="4" class=" bg-amber-400"></th>
                            <th colspan="1" class="flex items-center h-full border-r border-slate-900 justify-end">
                                <div class="min-w-24 h-full bg-amber-400 text-end justify-end border-l flex items-center border-slate-900 px-3 py-1 min-h-12 text-slate-900 font-black">
                                    PoinsH
                                </div>
                            </th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 w-32 font-bold">JamKer</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 w-32 font-bold text-blue-800">Persen</th>
                        <th class="border-r border-slate-900 px-3 py-1 bg-amber-400 font-black text-slate-900 uppercase">Harga V/m</th>
                        <th colspan="2" class="border-r border-slate-900 px-3 py-1 "></th>
                        <th class="border-r border-slate-900 px-3 py-1 bg-amber-400 font-black text-slate-900 uppercase">Harga V + Ongkos</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 "></th>
                        <th class="border-r border-slate-900 px-3 py-1 bg-amber-400 font-black text-slate-900 uppercase">Harga V + Penyusutan</th>
                    </tr> --}}

                    <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-900">
                        <th colspan="2" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">Total</th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 text-slate-900 text-center font-bold"></th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                            {{ number_format($rekap['total_kayu_masuk'], 0, ',', '.') }}
                        </th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">-</th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black">
                            {{ number_format($rekap['total_kubikasi_kayu_masuk'], 4, ',', '.') }}
                        </th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 bg-amber-400 text-slate-900 text-center font-black whitespace-nowrap">
                          Rp {{ number_format($rekap['total_poin_masuk'], 0, ',', '.') }}
                        </th>
                    
                        <th colspan="4" class=" bg-amber-400"></th>
                        <th colspan="1" class="flex items-center h-full border-r border-slate-900 justify-end">
                            <div class="min-w-24 h-full bg-amber-400 text-end justify-end border-l flex items-center border-slate-900 px-3 py-1 min-h-12 text-slate-900 font-black">
                                {{ number_format($rekap['total_kubikasi_veneer'], 4, ',', '.') }}
                            </div>

                        </th>

                        <th colspan="1" class="border-r whitespace-nowrap bg-[#FF88BA] border-slate-900 px-3 py-1 w-32 font-bold">
                            Rata - Rata
                        </th>
                        
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 w-32 font-bold text-blue-800">
                            {{ $rekap['rata_rata_rendemen'] }}
                        </th>
                        
                        <th class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                            {{-- Menghitung total harga v murni dari poin / m3 veneer --}}
                            Rp {{ number_format($rekap['total_harga_veneer'], 0, ',', '.') }}
                        </th>
                        
                        <th colspan="2" class="border-r border-slate-900 px-3 py-1 "></th>
                        <th class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                            Rp {{ number_format($rekap['total_harga_v_ongkos'], 0, ',', '.') }}
                        </th>
                        <th colspan="1" class="border-r border-slate-900 px-3 py-1 "></th>
                        <th class="border-r border-slate-900 px-3 py-1 bg-[#FF88BA] font-black text-slate-900 whitespace-nowrap">
                            Rp {{ number_format($rekap['total_harga_vop'], 0, ',', '.') }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-900 border-t border-slate-900 text-slate-900 font-bold">
                    <tr class="h-6 bg-slate-50">
                        <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td colspan="5" class="p-0 border-r border-slate-900 w-[352px]">
                            <div class="grid grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full">
                                <div class="w-full h-6 border-r border-slate-900"></div>
                                <div class="w-full h-6 border-r border-slate-900"></div>
                                <div class="w-full h-6 border-r border-slate-900"></div>
                                <div class="w-full h-6 border-r border-slate-900"></div>
                                <div class="w-full h-6"></div>
                            </div>
                        </td>

                        <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-slate-900"></td> 
                    </tr>
                    @foreach($laporan as $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="border-r border-slate-900 p-0 vertical-top">
                                <div class="flex flex-col divide-y divide-slate-900">
                                    @foreach ($item['outflow'] as $produksi)
                                        <div class="px-2 py-1 text-center text-slate-900 h-full min-h-[32px] flex items-center justify-center uppercase w-26 text-[10px] font-black">
                                            {{ $produksi['tgl'] }}
                                        </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="border-r border-slate-900 px-3 py-2 text-center text-emerald-600 font-black text-lg">✓</td>
                            <td class="border-r border-slate-900 px-3 py-2 text-center font-black text-slate-900">{{ $item['batch_info']['kode'] }}</td>
                            <td class="border-r border-slate-900 px-3 py-2 text-center text-slate-900 font-bold">{{ $item['summary']['total_kayu_masuk'] }}</td>
                            <td class="border-r border-slate-900 px-3 py-2"></td>
                            <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right font-black">{{ $item['summary']['total_masuk_m3'] }}</td>
                            <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right tabular-nums whitespace-nowrap font-black">{{ "Rp " . $item['summary']['total_poin'] }}</td>
                            
                            <td colspan="5" class="p-0 border-r w-[352px] border-slate-900">
                                <div class="flex flex-col divide-y w-full divide-slate-900 h-full">
                                    @foreach ($item['outflow'] as $produksi)
                                    <div class="grid grid-cols-[64px_64px_48px_80px_96px] w-full divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px] font-bold">
                                        <div class="text-center flex items-center justify-center h-full">{{ $produksi['panjang'] }}</div>
                                        <div class="text-center flex items-center justify-center h-full font-black text-slate-900">{{ $produksi['lebar'] }}</div>
                                        <div class="text-center flex items-center justify-center h-full">{{ $produksi['tebal'] }}</div>
                                        <div class="text-center font-mono flex items-center justify-center h-full font-black">{{ $produksi['total_banyak'] }}</div>
                                        <div class="bg-emerald-50/20 text-right pr-2 font-black h-full flex items-center justify-end text-emerald-800">{{ $produksi['total_kubikasi'] }}</div>
                                    </div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="border-r border-slate-900 p-0 text-[10px]">
                                <div class="flex flex-col divide-y divide-slate-900">
                                    @foreach ($item['outflow'] as $produksi)
                                        <div class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-32 font-bold">06:00 - 16:00</div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-center font-black text-blue-800">{{ $item['summary']['rendemen'] }}</td>
                            <td class="border-r border-slate-900 px-3 py-2 bg-emerald-50/30 text-right font-black text-emerald-800 whitespace-nowrap">Rp. {{ number_format($item['summary']['harga_veneer'], 0, ',', '.') }}</td>
                            
                            <td class="border-r border-slate-900 p-0">
                                <div class="flex flex-col divide-y divide-slate-900">
                                    @foreach ($item['outflow'] as $produksi)
                                        <div class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-24 uppercase font-bold">{{ $produksi['pekerja'] }}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="border-r border-slate-900 p-0 bg-amber-50/30">
                                <div class="flex flex-col divide-y divide-slate-900">
                                    @foreach ($item['outflow'] as $produksi)
                                        <div class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2 whitespace-nowrap font-bold">Rp. {{number_format($produksi['ongkos'], 0, ',', '.')}}</div>
                                    @endforeach
                                </div>
                            </td>

                            <td class="border-r border-slate-900 px-3 py-2 bg-orange-50/40 text-right font-black text-orange-900 whitespace-nowrap">Rp. {{number_format($item['summary']['harga_v_ongkos'], 0, ',', '.')}}</td>
                            
                            <td class="border-r border-slate-900 p-0 bg-blue-50/30">
                                <div class="flex flex-col divide-y divide-slate-900">
                                    @foreach ($item['outflow'] as $produksi)
                                        <div class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2 whitespace-nowrap font-bold">Rp. {{number_format($produksi['penyusutan'], 0, ',', '.')}}</div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-3 py-2 bg-yellow-50/50 text-right font-black text-slate-900 border-l border-slate-900 whitespace-nowrap">Rp. {{number_format($item['summary']['harga_vop'], 0, ',', '.')}}</td>
                        </tr>

                        @if(!$loop->last)
                        <tr class="h-6 bg-slate-50">
                            <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> 
                            <td colspan="5" class="p-0 border-r border-slate-900 w-[352px] h-full">
                                <div class="grid grid-cols-[64px_64px_48px_80px_96px] divide-x divide-slate-900 h-full">
                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                    <div class="w-full h-6  border-r border-slate-900"></div>
                                    <div class="w-full h-6 "></div>
                                </div>
                            </td>

                            <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-r border-slate-900"></td> <td class="border-slate-900"></td> 
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-[10px] text-slate-600 font-bold uppercase">
            * Generated automatically by Veneer Production System - Export Preview Mode
        </div>

        <div class="fixed bottom-0 left-0 right-0 bg-[#217346] border-t border-slate-900 flex items-center px-4 no-print z-50 h-10 shadow-[0_-2px_10px_rgba(0,0,0,0.1)]">
            <div class="flex border-r border-green-800 pr-2 mr-2">
                <button class="p-1 hover:bg-green-700 text-white font-black"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"/></svg></button>
                <button class="p-1 hover:bg-green-700 text-white font-black"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/></svg></button>
            </div>

            <div class="flex items-end h-full">
                @foreach($sheets as $sheet)
                    <a href="{{ url()->current() }}?bulan={{ $selectedBulan }}&tahun={{ $selectedTahun }}&sheet={{ urlencode($sheet) }}"                         
                    class="px-4 py-1 text-xs font-black transition-all flex items-center h-[85%] 
                    {{ $activeSheet == $sheet 
                            ? 'bg-white text-green-800 border-x border-t border-slate-400 rounded-t shadow-sm' 
                            : 'text-white hover:bg-green-700 border-x border-transparent' }}">
                       KAYU {{ $sheet }}
                    </a>
                @endforeach
            </div>

            <div class="ml-auto text-[10px] text-green-100 font-black font-mono uppercase">
                Veneer System v2.0
            </div>
        </div>    
    </div>
</body>
</html>