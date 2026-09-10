<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Semua - Nota & Surat Jalan</title>
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; background: #525659; }
        iframe { width: 100%; height: 100vh; border: none; display: none; }
    </style>
</head>
<body>
    <div style="text-align: center; padding: 50px; color: white; font-family: sans-serif;">
        <h2 style="margin-bottom: 10px;">Mempersiapkan dokumen cetak...</h2>
        <p style="margin-bottom: 30px;">Jika tab Surat Jalan tidak terbuka otomatis, silakan klik tombol di bawah ini.</p>
        
        <a id="btnSJ" href="{{ route('surat-jalan.bk', ['nota' => $record->id, 'jenis' => $jenis]) }}" target="_blank" style="background: #10b981; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-right: 15px;">
            Buka Surat Jalan
        </a>
        <a href="{{ route($jenis === 'sales' ? 'nota-bk.nota-sales' : 'nota-bk.nota-kantor', $record) }}" style="background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;">
            Lanjutkan Buka Nota
        </a>
    </div>
    
    <script>
        window.onload = function() {
            setTimeout(() => {
                let opened = window.open(document.getElementById('btnSJ').href, '_blank');
                if (opened) {
                    window.location.href = "{{ route($jenis === 'sales' ? 'nota-bk.nota-sales' : 'nota-bk.nota-kantor', $record) }}";
                }
            }, 500);
        };
    </script>
</body>
</html>
