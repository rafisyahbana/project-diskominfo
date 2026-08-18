<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Keterangan Domisili - {{ $permohonan->nomor_surat }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; color: #000; }

        .kop-surat {
            border-bottom: 3px solid #000;
            padding-bottom: 8px;
            margin-bottom: 16px;
            display: table;
            width: 100%;
        }
        .kop-logo { display: table-cell; width: 90px; vertical-align: middle; text-align: center; }
        .kop-logo .logo-placeholder {
            width: 75px; height: 75px;
            border: 2px solid #333;
            display: block;
            line-height: 75px;
            text-align: center;
            font-size: 9pt;
            color: #666;
        }
        .kop-teks { display: table-cell; text-align: center; vertical-align: middle; }
        .kop-teks .pemerintah    { font-size: 11pt; }
        .kop-teks .instansi      { font-size: 15pt; font-weight: bold; text-transform: uppercase; }
        .kop-teks .unit-kerja    { font-size: 11pt; font-weight: bold; }
        .kop-teks .alamat        { font-size: 9pt; }

        .judul-surat {
            text-align: center;
            margin: 20px 0 4px;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .nomor-surat {
            text-align: center;
            font-size: 11pt;
            margin-bottom: 20px;
        }

        .dasar { margin-bottom: 12px; }
        .dasar-label { font-weight: bold; }

        .pembuka { margin-bottom: 16px; line-height: 1.6; }

        table.data-pihak {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 16px 20px;
        }
        table.data-pihak td { padding: 2px 4px; vertical-align: top; }
        table.data-pihak td:first-child { width: 200px; }
        table.data-pihak td:nth-child(2) { width: 16px; text-align: center; }

        .penutup { line-height: 1.6; margin-bottom: 30px; }

        .ttd-wrapper {
            display: table;
            width: 100%;
        }
        .ttd-kanan {
            display: table-cell;
            width: 50%;
            text-align: center;
            float: right;
        }
        .ttd-kiri {
            display: table-cell;
            width: 50%;
        }
        .ttd-jabatan { font-size: 11pt; margin-bottom: 80px; }
        .ttd-nama    { font-size: 11pt; font-weight: bold; text-decoration: underline; }
        .ttd-nip     { font-size: 10pt; }

        .footer-note {
            margin-top: 40px;
            font-size: 9pt;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    {{-- KOP SURAT --}}
    <div class="kop-surat">
        <div class="kop-logo">
            <span class="logo-placeholder">[LOGO]</span>
        </div>
        <div class="kop-teks">
            <div class="pemerintah">PEMERINTAH DAERAH KABUPATEN / KOTA [NAMA DAERAH]</div>
            <div class="instansi">Dinas Komunikasi dan Informatika</div>
            <div class="unit-kerja">Bidang Layanan Publik Digital</div>
            <div class="alamat">Jl. Contoh No. 1, [Kota], Telp. (000) 000-0000 | diskominfo@[domain].go.id</div>
        </div>
    </div>

    {{-- JUDUL --}}
    <div class="judul-surat">Surat Keterangan Domisili</div>
    <div class="nomor-surat">Nomor: {{ $permohonan->nomor_surat }}</div>

    {{-- DASAR --}}
    <div class="dasar">
        <span class="dasar-label">Dasar:</span>
        Permohonan yang bersangkutan tertanggal {{ $permohonan->created_at->format('d F Y') }}.
    </div>

    {{-- PEMBUKA --}}
    <div class="pembuka">
        Yang bertanda tangan di bawah ini, Kepala Dinas Komunikasi dan Informatika [Nama Daerah],
        dengan ini menerangkan bahwa:
    </div>

    {{-- DATA PEMOHON --}}
    <table class="data-pihak">
        @php
            $labelMap = \App\Services\ReferensiService::LABEL_FIELD;
            $dataForm = $permohonan->data_form ?? [];
        @endphp
        <tr>
            <td>Nama Lengkap</td>
            <td>:</td>
            <td><strong>{{ $dataForm['nama_lengkap'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td>NIK</td>
            <td>:</td>
            <td>{{ $permohonan->nik }}</td>
        </tr>
        @foreach($dataForm as $key => $val)
            @if (!in_array($key, ['nama_lengkap']))
                <tr>
                    <td>{{ $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                    <td>:</td>
                    <td>{{ $val }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    {{-- ISI SURAT --}}
    <div class="pembuka">
        adalah benar-benar berdomisili / bertempat tinggal di wilayah kami sesuai dengan alamat
        yang tercantum di atas. Surat keterangan ini dibuat untuk digunakan sebagaimana mestinya.
    </div>

    {{-- PENUTUP --}}
    <div class="penutup">
        Demikian surat keterangan ini dibuat dengan sesungguhnya untuk dapat dipergunakan
        sebagaimana mestinya.
    </div>

    {{-- TANDA TANGAN --}}
    <div class="ttd-wrapper">
        <div class="ttd-kiri"></div>
        <div class="ttd-kanan">
            <div class="ttd-jabatan">
                [Kota], {{ now()->translatedFormat('d F Y') }}<br>
                Kepala Dinas Komunikasi dan Informatika<br>
                [Nama Daerah]
            </div>
            <div class="ttd-nama">[Nama Pejabat, M.Si]</div>
            <div class="ttd-nip">NIP. 19XX XXXX XXXX X XXX X</div>
        </div>
    </div>

    <div class="footer-note">
        Dokumen ini diterbitkan secara digital melalui Sistem Layanan Surat Diskominfo.
        Nomor Permohonan: {{ $permohonan->id }} | Diterbitkan: {{ $tanggalTerbit }}
    </div>

</body>
</html>
