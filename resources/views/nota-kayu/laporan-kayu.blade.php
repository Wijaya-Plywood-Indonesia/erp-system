<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Kayu Masuk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/confetti.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background-color: #f4f7f6;
        }

        h2 {
            color: #2c3e50;
        }

        .filter-box {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .flex-container {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        label {
            font-size: 12px;
            font-weight: bold;
            color: #7f8c8d;
        }

        input {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            min-width: 200px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 13px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-filter {
            background: #3498db;
        }

        .btn-excel {
            background: #27ae60;
        }

        .btn-reset {
            background: #95a5a6;
        }

        .btn:hover {
            opacity: 0.8;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background: white;
        }

        th,
        td {
            border: 1px solid #444;
            padding: 8px;
            text-align: center;
        }

        th {
            background: #f1f1f1;
            font-size: 13px;
        }
    </style>
</head>

<body>

    <h2>Laporan Kayu Masuk</h2>

    <div class="filter-box">
        <form action="{{ url()->current() }}" method="GET" class="flex-container">
            <div class="form-group">
                <label>Mulai Dari</label>
                <input type="text" id="dari" name="dari" placeholder="Tanggal Awal" value="{{ request('dari') }}">
            </div>

            <div class="form-group">
                <label>Sampai Dengan</label>
                <input type="text" id="sampai" name="sampai" placeholder="Tanggal Akhir" value="{{ request('sampai') }}">
            </div>

            <button type="submit" class="btn btn-filter">Filter</button>
            <a href="{{ route('laporan.kayu-masuk') }}" class="btn btn-reset">Reset</a>

            <a href="{{ route('laporan.kayu-masuk.export', request()->all()) }}" class="btn btn-excel">
                <i class="fa-solid fa-arrow-down"></i> Export Excel
            </a>
        </form>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama</th>
                <th>Seri</th>
                <th>Panjang</th>
                <th>Jenis</th>
                <th>Lahan</th>
                <th>Banyak</th>
                <th>M3</th>
                <th>Poin</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data as $row)
            <tr>
                <td>{{ \Carbon\Carbon::parse($row->tanggal)->format('d/m/Y') }}</td>
                <td>{{ $row->nama }}</td>
                <td>{{ $row->seri }}</td>
                <td>{{ $row->panjang }}</td>
                <td>{{ $row->jenis }}</td>
                <td>{{ $row->lahan }}</td>
                <td>{{ $row->banyak }}</td>
                <td>{{ number_format($row->m3, 4) }}</td>
                <td>{{ number_format($row->poin, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="padding: 30px; color: #999;">Data tidak ditemukan.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        const startPicker = flatpickr("#dari", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d F Y",
            onChange: function(selectedDates, dateStr) {
                endPicker.set("minDate", dateStr);
            }
        });

        const endPicker = flatpickr("#sampai", {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d F Y",
            onChange: function(selectedDates, dateStr) {
                startPicker.set("maxDate", dateStr);
            }
        });
    </script>
</body>

</html>