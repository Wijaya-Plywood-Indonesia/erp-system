<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Persentase Kayu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased text-slate-800">

    <div class="p-6">
        <div class="overflow-x-auto rounded-lg shadow-sm border border-slate-900">
            <table class="w-full border-collapse bg-white text-sm font-sans">
                <thead>
                    <tr class="bg-slate-100/80 border-b border-slate-900 text-slate-600">
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-24">Tanggal</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2">Habis</th>
                        <th colspan="5" class="border-r border-slate-900 px-3 py-2">Kayu</th>
                        <th colspan="5" rowspan="2" class="border-r border-slate-900 p-0 w-[352px]">
                            <table class="w-full table-fixed border-collapse">
                                <thead>
                                    <tr>
                                        <th colspan="5" class="border-b border-slate-900 py-2 text-center uppercase tracking-wider">Veneer</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="5" class="grid w-[352px] grid-cols-[64px_64px_48px_80px_96px]  divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px]">
                                            <div class="text-center flex items-center justify-center h-full">P</div>
                                            <div class="text-center flex items-center justify-center h-full">L</div>
                                            <div class="text-center flex items-center justify-center h-full">T</div>
                                            <div class="text-center font-mono flex items-center justify-center h-full">TOTAL</div>
                                            <div class="bg-emerald-50/20 text-right pr-2 font-medium h-full flex items-center justify-end">M³</div>
                                        </th>
                                    </tr>
                                </thead>
                            </table>
                        </th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 w-32">Jam Kerja</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 italic text-blue-700">%</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-emerald-100/50 text-emerald-800 font-bold uppercase text-[10px]">Harga Veneer / m³</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-700 w-24 text-center p-0">Pekerja</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-amber-50/50 text-amber-700 w-32 text-center p-0">Ongkos / pkj</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-orange-100/40 text-orange-800 font-bold uppercase text-[10px]">Harga V + Ongkos</th>
                        <th rowspan="2" class="border-r border-slate-900 px-3 py-2 bg-blue-50/50 text-blue-700 text-[10px] w-32 text-center p-0">Penyusutan</th>
                        <th rowspan="2" class="px-3 py-2 bg-yellow-100/40 text-yellow-800 font-bold uppercase text-[10px]">Harga VOP</th>
                    </tr>
                    <tr class="bg-slate-50 border-b border-slate-900 text-slate-900 uppercase">
                        <th class="border-r border-slate-900 px-2 py-1">Lahan</th>
                        <th class="border-r border-slate-900 px-2 py-1">Batang</th>
                        <th class="border-r border-slate-900 px-2 py-1">Pecah</th>
                        <th class="border-r border-slate-900 px-2 py-1 bg-orange-50/30">m³</th>
                        <th class="border-r border-slate-900 px-2 py-1 bg-yellow-50/30">Poin</th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-900 border-t border-slate-900 text-slate-900">
                    @foreach($laporan as $item)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="border-r border-slate-900 p-0 vertical-top">
                            <div class="flex flex-col divide-y divide-slate-900">
                                @foreach ($item['outflow'] as $produksi)
                                    <div class="px-2 py-1 text-center h-full min-h-[32px] flex items-center justify-center uppercase w-24 text-[10px]">
                                        {{ $produksi['tgl'] }}
                                    </div>
                                @endforeach
                            </div>
                        </td>

                        <td class="border-r border-slate-900 px-3 py-2 text-center text-emerald-600 font-bold">✓</td>
                        <td class="border-r border-slate-900 px-3 py-2 text-center font-bold text-slate-700">{{ $item['batch_info']['kode'] }}</td>
                        <td class="border-r border-slate-900 px-3 py-2 text-center text-slate-600">{{ $item['summary']['total_kayu_masuk'] }}</td>
                        <td class="border-r border-slate-900 px-3 py-2"></td>
                        <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right font-medium">{{ $item['summary']['total_masuk_m3'] }}</td>
                        <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-right tabular-nums">{{ "Rp " . $item['summary']['total_poin'] }}</td>
                        
                        <td colspan="5" class="p-0 border-r w-[352px] border-slate-900">
                            <div class="flex flex-col divide-y w-full divide-slate-900 h-full">
                                @foreach ($item['outflow'] as $produksi)
                                <div class="grid grid-cols-[64px_64px_48px_80px_96px] w-full  divide-x divide-slate-900 h-full min-h-[32px] items-center text-[11px]">
                                    <div class="text-center flex items-center justify-center h-full">{{ $produksi['panjang'] }}</div>
                                    <div class="text-center flex items-center justify-center h-full">{{ $produksi['lebar'] }}</div>
                                    <div class="text-center flex items-center justify-center h-full">{{ $produksi['tebal'] }}</div>
                                    <div class="text-center font-mono flex items-center justify-center h-full">{{ $produksi['total_banyak'] }}</div>
                                    <div class="bg-emerald-50/20 text-right pr-2 font-medium h-full flex items-center justify-end">{{ $produksi['total_kubikasi'] }}</div>
                                </div>
                                @endforeach
                            </div>
                        </td>

                        <td class="border-r border-slate-900 p-0 text-[10px] italic">
                            <div class="flex flex-col divide-y divide-slate-900">
                                @foreach ($item['outflow'] as $produksi)
                                    <div class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-32">06:00 - 16:00</div>
                                @endforeach
                            </div>
                        </td>

                        <td class="border-r border-slate-900 px-3 py-2 bg-blue-50/30 text-center font-bold text-blue-700">{{ $item['summary']['rendemen'] }}</td>
                        <td class="border-r border-slate-900 px-3 py-2 bg-emerald-50/30 text-right font-semibold text-emerald-700">{{ $item['summary']['total_keluar_m3'] }}</td>
                        
                        <td class="border-r border-slate-900 p-0">
                            <div class="flex flex-col divide-y divide-slate-900">
                                @foreach ($item['outflow'] as $produksi)
                                    <div class="px-2 py-1 text-center min-h-[32px] flex items-center justify-center w-24 uppercase">{{ $produksi['pekerja'] }}</div>
                                @endforeach
                            </div>
                        </td>
                        <td class="border-r border-slate-900 p-0 bg-amber-50/30">
                            <div class="flex flex-col divide-y divide-slate-900">
                                @foreach ($item['outflow'] as $produksi)
                                    <div class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2">Rp. {{number_format($produksi['ongkos'], 0, ',', '.')}}</div>
                                @endforeach
                            </div>
                        </td>

                        <td class="border-r border-slate-900 px-3 py-2 bg-orange-50/40 text-right font-bold text-orange-800">Rp. {{number_format($item['summary']['harga_v_ongkos'], 0, ',', '.')}}</td>
                        
                        <td class="border-r border-slate-900 p-0 bg-blue-50/30">
                            <div class="flex flex-col divide-y divide-slate-900">
                                @foreach ($item['outflow'] as $produksi)
                                    <div class="px-2 py-1 text-right min-h-[32px] flex items-center justify-end w-32 pr-2">Rp. {{number_format($produksi['penyusutan'], 0, ',', '.')}}</div>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-3 py-2 bg-yellow-50/50 text-right font-black text-slate-900 border-l border-slate-900 italic">Rp. {{number_format($item['summary']['harga_vop'], 0, ',', '.')}}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div class="mt-4 text-[10px] text-slate-400 italic">
            * Generated automatically by Veneer Production System - Export Preview Mode
        </div>
    </div>


</body>
</html>