@php
    // Brand ditentukan di satu tempat: App\Support\Brand + config/brand.php
    // Tes lokal: isi BRAND_OVERRIDE=wijaya di .env
    $b = \App\Support\Brand::current();
    $loggedIn = auth()->check();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $b['company'] }} - Sistem ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Inter", system-ui, sans-serif;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex; align-items: center; justify-content: center;
            padding: 1.5rem;
            color: #fff;
            background-color: #0f172a;
            background-image:
                linear-gradient(135deg, rgba(15,23,42,{{ $b['overlay_start'] }}) 0%, rgba(15,23,42,{{ $b['overlay_end'] }}) 100%),
                url("{{ asset($b['background']) }}");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }

        .panel {
            width: 100%; max-width: 30rem; text-align: center;
            padding: 2.75rem 2.25rem;
            background: {{ $b['card_bg'] }};
            -webkit-backdrop-filter: blur({{ $b['card_blur'] }}) saturate({{ $b['card_saturate'] }});
            backdrop-filter: blur({{ $b['card_blur'] }}) saturate({{ $b['card_saturate'] }});
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 1.75rem;
            box-shadow: 0 30px 60px -15px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.2);
        }
        .logo { display: flex; justify-content: center; margin-bottom: 1.25rem; }
        .logo img { height: {{ $b['landing_logo_height'] }}; width: auto; max-width: 85%; object-fit: contain; filter: drop-shadow(0 4px 12px rgba(0,0,0,.45)); }

        .badge {
            display: inline-block; padding: .35rem .9rem; border-radius: 999px;
            font-size: .72rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
            color: #fcd34d; background: rgba(251,191,36,.12); border: 1px solid rgba(251,191,36,.35);
            margin-bottom: 1rem;
        }
        h1 { font-size: 1.9rem; font-weight: 800; line-height: 1.2; letter-spacing: -.01em; margin-bottom: .6rem; }
        p { color: rgba(255,255,255,.75); font-size: .98rem; margin-bottom: 2rem; }

        .btn {
            display: flex; align-items: center; justify-content: center; gap: .5rem;
            width: 100%; padding: .85rem 1.25rem; border-radius: .75rem;
            background: #fbbf24; color: #1f2937; font-weight: 700; font-size: 1rem;
            box-shadow: 0 10px 25px -8px rgba(245,158,11,.6);
            transition: transform .15s, box-shadow .15s, background .2s;
        }
        .btn:hover { background: #fcd34d; transform: translateY(-2px); box-shadow: 0 14px 30px -8px rgba(245,158,11,.8); }

        .site-link {
            display: inline-flex; align-items: center; gap: .4rem; margin-top: 1.25rem;
            font-size: .9rem; color: rgba(255,255,255,.8); transition: color .2s;
        }
        .site-link:hover { color: #fbbf24; }

        .foot { margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid rgba(255,255,255,.12); font-size: .78rem; color: rgba(255,255,255,.55); }

        @media (max-width: 768px) {
            body {
                background-attachment: scroll;
                background-position: {{ $b['bg_position_mobile'] }};
            }
            .panel { padding: 2.25rem 1.5rem; background: {{ $b['card_bg_mobile'] }}; }
            h1 { font-size: 1.6rem; }
        }
    </style>
</head>
<body>
    <main class="panel">
        <div class="logo">
            <img src="{{ asset($b['logo']) }}" alt="Logo {{ $b['name'] }}" id="brandLogo">
        </div>

        <span class="badge">Sistem Internal</span>
        <h1>Sistem ERP Produksi</h1>
        <p>Masuk untuk mengakses data produksi, stok, dan laporan {{ $b['company'] }}.</p>

        {{-- Teks tombol selalu sama. Tujuan: sudah login -> /admin, belum -> halaman login --}}
        <a href="{{ $loggedIn ? url('/admin') : route('filament.admin.auth.login') }}" class="btn">
            Masuk ke Sistem
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </a>

        @if ($b['website'])
            <a href="{{ $b['website'] }}" target="_blank" rel="noopener" class="site-link">
                Kunjungi website perusahaan
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        @endif

        <div class="foot">&copy; {{ date('Y') }} {{ $b['company'] }}</div>
    </main>

    <script>
        (function () {
            // Logo: hapus latar putih, jadi putih transparan, potong ruang kosong
            var img = document.getElementById('brandLogo');
            if (!img) return;
            function run() {
                try {
                    var scale = Math.min(1, 800 / img.naturalWidth);
                    var w = Math.round(img.naturalWidth * scale);
                    var h = Math.round(img.naturalHeight * scale);
                    var c = document.createElement('canvas');
                    c.width = w; c.height = h;
                    var ctx = c.getContext('2d');
                    ctx.drawImage(img, 0, 0, w, h);
                    var data = ctx.getImageData(0, 0, w, h);
                    var p = data.data;
                    var minX = w, minY = h, maxX = 0, maxY = 0;
                    for (var y = 0; y < h; y++) {
                        for (var x = 0; x < w; x++) {
                            var i = (y * w + x) * 4;
                            var lum = p[i] * 0.299 + p[i + 1] * 0.587 + p[i + 2] * 0.114;
                            var a = Math.max(0, Math.min(255, (255 - lum) * 1.6));
                            a = a * (p[i + 3] / 255);
                            p[i] = 255; p[i + 1] = 255; p[i + 2] = 255; p[i + 3] = a;
                            if (a > 40) {
                                if (x < minX) minX = x; if (x > maxX) maxX = x;
                                if (y < minY) minY = y; if (y > maxY) maxY = y;
                            }
                        }
                    }
                    ctx.putImageData(data, 0, 0);
                    if (maxX > minX && maxY > minY) {
                        var pad = 6;
                        minX = Math.max(0, minX - pad); minY = Math.max(0, minY - pad);
                        maxX = Math.min(w - 1, maxX + pad); maxY = Math.min(h - 1, maxY + pad);
                        var cw = maxX - minX + 1, ch = maxY - minY + 1;
                        var c2 = document.createElement('canvas');
                        c2.width = cw; c2.height = ch;
                        c2.getContext('2d').drawImage(c, minX, minY, cw, ch, 0, 0, cw, ch);
                        img.src = c2.toDataURL('image/png');
                    } else {
                        img.src = c.toDataURL('image/png');
                    }
                } catch (e) { console.warn('Logo tidak bisa diproses', e); }
            }
            if (img.complete && img.naturalWidth) run();
            else img.addEventListener('load', run, { once: true });
        })();
    </script>
</body>
</html>