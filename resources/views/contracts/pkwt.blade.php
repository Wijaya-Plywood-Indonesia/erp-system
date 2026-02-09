<!DOCTYPE html>
<html lang="id">
  <head>
    <meta charset="UTF-8" />
    <title>Perjanjian Kerja PKWT</title>

   <style>
    /* ========================
       SETTING KERTAS F4 / LEGAL
       ======================== */
    @page {
        size:  21.59cm 33.02cm; /* F4 (Legal) */
        margin: 1cm;          /* Margin printing */
    }

    body {
        font-family: "Times New Roman", Times, serif;
        font-size: 9pt;
        margin: 0.5cm;                 /* WAJIB: biarkan margin dikendalikan @page */
        line-height: 1.15;
        text-align: justify;
    }

    .title {
        text-align: center;
        font-size: 11pt;
        font-weight: bold;
        text-decoration: underline;
        margin-bottom: 5px;
        line-height: 1.2;
    }

    .subtitle {
        text-align: center;
        margin-bottom: 25px;
        font-size: 9pt;
        font-weight: bold;
    }

    p {
        margin: 0;
        padding: 0;
        margin-bottom: 6px; /* jarak antar paragraf Word */
    }

    .section-title {
        font-size: 9pt;
        font-weight: bold;
        margin-top: 12px;
        margin-bottom: 6px;
    }

    /* Tabel tanda tangan */
    table {
        font-family: "Times New Roman", Times, serif;
        font-size: 9pt;
    }
</style>
  </head>
  <body>
    <h2 class="title">PERJANJIAN WAKTU KERJA TERTENTU (PKWT)</h2>

    <div class="subtitle">
      NOMOR: <strong>{{ $record->no_kontrak }}</strong>
    </div>

    <p>Yang bertanda tangan di bawah ini :</p>

    <table
      style="
        width: 100%;
        font-size: 9pt;
        line-height: 1.15;
        margin-bottom: 10px;
      "
    >
      <tr>
        <td style="width: 130px">Nama</td>
        <td style="width: 10px">:</td>
        <td><strong>Anis Rusnaa’ifah</strong></td>
      </tr>
      <tr>
        <td>Alamat</td>
        <td>:</td>
        <td>Jl. Dr. Cipto 1B No.16 Bedali Kalianyar Lawang</td>
      </tr>
      <tr>
        <td>Bertindak atas nama</td>
        <td>:</td>
        <td>
          <strong>{{ $record->karyawan_di }}</strong>
        </td>
      </tr>
    </table>

    <p>
      Dalam hal ini bertindak mewakili atas nama Perusahaan
      <strong>{{ $record->karyawan_di }}</strong
      >, selanjutnya disebut sebagai <strong>PIHAK PERTAMA</strong>.
    </p>
    <table
      style="
        width: 100%;
        font-size: 9pt;
        line-height: 1.15;
        margin-bottom: 10px;
      "
    >
      <tr>
        <td style="width: 130px">Nama Lengkap</td>
        <td style="width: 10px">:</td>
        <td>
          <strong>{{ $record->nama }}</strong>
        </td>
      </tr>
      <tr>
        <td>No KTP/SIM</td>
        <td>:</td>
        <td>
          <strong>{{ $record->nik }}</strong>
        </td>
      </tr>
      <tr>
        <td>Tempat, Tanggal Lahir</td>
        <td>:</td>
        <td>
          <strong>{{ $record->tempat_tanggal_lahir }}</strong>
        </td>
      </tr>
      <tr>
        <td>Alamat</td>
        <td>:</td>
        <td>
          <strong>{{ $record->alamat }}</strong>
        </td>
      </tr>
    </table>

    <p>Selanjutnya disebut sebagai <strong>PIHAK KEDUA</strong>.</p>
    <p>
      Pada hari ini, tanggal
      <strong
        >{{ \Carbon\Carbon::parse($record->kontrak_mulai)->translatedFormat('d F Y') }}</strong
      >, kedua belah pihak secara sadar dan tanpa paksaan telah mengadakan
      perjanjian kontrak kerja, dengan isi pernyataan meliputi :
    </p>

    <div style="text-align: center; font-weight: bold; margin-top: 10px">
      Pasal 1
    </div>

    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Ketentuan Umum
    </div>

    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      <li>
        Dengan ditanda tanganinya Kontrak Kerja ini berarti PIHAK KEDUA telah
        mengetahui dan harus patuh terhadap Peraturan Perusahaan atau
        peraturan-peraturan lain yang berlaku di PIHAK PERTAMA.
      </li>
      <li>
        Demi kepentingan PIHAK PERTAMA dalam hal pengaturan kerja lembur maka
        PIHAK KEDUA menyatakan kesediaannya untuk memenuhi peraturan tersebut.
      </li>
    </ol>
    <!-- PASAL 2 -->
    <div style="text-align: center; font-weight: bold; margin-top: 20px">
      Pasal 2
    </div>

    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Penunjukan Sebagai Karyawan
    </div>

    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      <li>
        PIHAK PERTAMA memberi pekerjaan kepada PIHAK KEDUA, dan PIHAK KEDUA
        telah mengakui menerima pekerjaan dari PIHAK PERTAMA.
      </li>

      <li>
        Dalam kontrak kerja ini, PIHAK KEDUA melaksanakan pekerjaan sebagai
        <strong>{{ $record->jabatan }}</strong> di Perusahaan milik PIHAK
        PERTAMA yang berlokasi di {{ $record->alamat_perusahaan }}.
      </li>

      <li>
        Pekerjaan sebagaimana tersebut pada ayat 2 (dua) pasal ini dilaksanakan
        oleh PIHAK KEDUA selama
        <strong>{{ $record->durasi_kontrak }} hari</strong>, terhitung mulai
        tanggal
        <strong
          >{{ \Carbon\Carbon::parse($record->kontrak_mulai)->translatedFormat('d F Y') }}</strong
        >
        sampai dengan
        <strong
          >{{ \Carbon\Carbon::parse($record->kontrak_selesai)->translatedFormat('d F Y') }}</strong
        >.
      </li>

      <li>
        Apabila masa kontrak telah selesai sesuai tanggal berakhirnya kontrak
        maka hubungan kerja berakhir tanpa ada kewajiban PIHAK PERTAMA
        memberikan pesangon, uang jasa ataupun ganti kerugian lainnya kepada
        PIHAK KEDUA. Apabila diperlukan, kontrak dapat diperpanjang sesuai
        dengan kebutuhan perusahaan dan akan ditentukan kemudian hari.
      </li>

      <li>
        Selama masa berjalannya kontrak, PIHAK KEDUA dapat sewaktu-waktu
        mengundurkan diri dengan pemberitahuan terlebih dahulu kepada PIHAK
        PERTAMA.
      </li>
    </ol>

    <!-- PASAL 3 -->
    <div style="text-align: center; font-weight: bold; margin-top: 20px">
      Pasal 3
    </div>

    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Hak dan Kewajiban
    </div>

    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      <li>
        PIHAK PERTAMA dan PIHAK KEDUA secara bersama-sama berkewajiban membina
        hubungan kerja yang harmonis agar tercipta ketenangan kerja dan
        ketentraman usaha.
      </li>

      <li>
        PIHAK KEDUA berhak:
        <br />
        • menerima / mendapatkan gaji sebesar lima ribu rupiah (Rp.5000) perjam.
        Dimulai pada jam 06.00 – 14.00 dengan waktu istirahat satu (1) jam.
        Terhitung total jam kerja tujuh (7) jam dengan total gaji tiga puluh
        lima ribu rupiah (Rp.35.000) perhari.
      </li>

      <li>
        PIHAK KEDUA berkewajiban:
        <br />
        • mentaati segala peraturan yang diberikan PIHAK PERTAMA.<br />
        • merahasiakan semua informasi sekaligus hal-hal penting yang
        berhubungan dengan PIHAK PERTAMA, yakni seputar informasi yang diterima
        atau diketahui olehnya – baik karena jabatannya, atau karena sebab lain,
        baik selama PIHAK KEDUA bekerja dan PIHAK PERTAMA hingga setelah kontrak
        kerja dalam perjanjian ini telah berakhir.<br />
        • menyerahkan semua informasi sekaligus hal-hal penting yang berhubungan
        dengan pihak pertama yang diterima atau diketahui olehnya - baik karena
        jabatannya, atau karena sebab lain. Hal ini berlaku untuk semua
        informasi maupun data dalam bentuk hard copy, disket, email, USB, CD,
        maupun dalam bentuk media lainnya, karena semuanya wajib diserahkan
        kepada atasannya.
      </li>
      <li>
        Tidak melakukan double job/bekerja ganda/bekerja pada Perusahaan
        lain/usaha lain/membantu usaha keluarga/dan sebagainya, selama dalam
        masa kontrak kerja.
      </li>
    </ol>
    <div style="text-align: center; font-weight: bold; margin-top: 20px">
      Pasal 4
    </div>

    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Sanksi
    </div>

    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      <li>
        Bilamana PIHAK KEDUA ternyata tidak memenuhi kewajiban-kewajiban
        tersebut di atas, PIHAK PERTAMA berwenang memberikan teguran atau
        peringatan baik lisan maupun tulisan kepada PIHAK KEDUA. 2. Apabila
        PIHAK KEDUA tidak
      </li>
      <li>
        mengindahkan teguran atau peringatan tersebut, maka PIHAK KEDUA dapat
        dikenakan pemutusan hubungan kerja sebelum masa kontrak kerjanya
        berakhir, tanpa adanya kewajiban PIHAK PERTAMA memberikan pesangon, uang
        jasa, ataupun ganti kerugian lainnya kepada PIHAK KEDUA.
      </li>
    </ol>

    <div style="text-align: center; font-weight: bold; margin-top: 20px">
      Pasal 5
    </div>
    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Sanksi
    </div>

    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      WAKTU DAN TEMPAT KERJA PIHAK KEDUA wajib mentaati waktu kerja sebagai
      berikut : Senin-Sabtu : Jam 06.00-14.00 WIB. Isirahat : Jam 12.00 – 13.00
      WIB atau 11.00-12.00. *Ketidak hadiran diperhitungkan waktu.
    </ol>

    <div style="text-align: center; font-weight: bold; margin-top: 20px">
      Pasal 6
    </div>
    <div
      style="
        text-align: center;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 10px;
      "
    >
      Sanksi
    </div>
    <ol
      style="margin: 0; padding-left: 18px; font-size: 9pt; line-height: 1.15"
    >
      <li>
        Bila terjadi perselisihan antara kedua belah pihak dalam melaksanakan
        Kontrak Kerja ini, maka kedua belah pihak akan menyelesaikannya secara
        musyawarah.
      </li>
      <li>
        Apabila penyelesaian pada ayat satu(1) di atas tidak berhasil, maka
        Kontrak Kerja tidak diperpanjang oleh kedua belah pihak.
      </li>
    </ol>
    <br />
   <br><br>

<table style="width: 100%; font-size: 9pt; line-height: 1.15; font-family: 'Times New Roman', Times, serif;">
    <tr>
        <!-- PIHAK PERTAMA -->
        <td style="width: 50%; text-align: left;">
            PIHAK PERTAMA
        </td>

        <!-- Tanggal + PIHAK KEDUA -->
        <td style="width: 50%; text-align: left;">
            Malang, {{ \Carbon\Carbon::parse($record->kontrak_mulai ?? now())->translatedFormat('d F Y') }} <br>
            PIHAK KEDUA
        </td>
    </tr>

    <!-- Space untuk tanda tangan -->
    <tr>
        <td style="height: 80px; vertical-align: bottom; text-align: left;">
            @if($record->bukti_ttd)
                <img src="{{ asset('storage/'.$record->bukti_ttd) }}" style="height:75px;">
            @endif
        </td>

        <td style="vertical-align: bottom; text-align: left;">
            {{-- tanda tangan pihak kedua jika suatu saat pakai gambar --}}
        </td>
    </tr>

    <tr>
        <td style="text-align: left;">
            <strong>(Anis Rusnaa’ifah)</strong>
        </td>
        <td style="text-align: left;">
            <strong>({{ $record->nama }})</strong>
        </td>
    </tr>
</table>

    </div>
  </body>
</html>
