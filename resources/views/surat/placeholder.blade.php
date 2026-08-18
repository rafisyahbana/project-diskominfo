<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $judulSurat }} - {{ $permohonan->nomor_surat }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12pt; color: #000; }
        h1 { font-size: 14pt; text-align: center; text-decoration: underline; text-transform: uppercase; }
        .nomor { text-align: center; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td { padding: 4px 8px; vertical-align: top; }
        td:first-child { width: 200px; font-weight: bold; }
        td:nth-child(2) { width: 16px; }
        .note { font-size: 9pt; color: #888; margin-top: 30px; border-top: 1px solid #ccc; padding-top: 6px; }
    </style>
</head>
<body>
    <h1>{{ $judulSurat }}</h1>
    <div class="nomor">Nomor: {{ $permohonan->nomor_surat }}</div>

    <p><em>Template resmi untuk surat jenis ini sedang dalam pengembangan.
    Berikut adalah data permohonan yang tercatat:</em></p>

    @php $labelMap = \App\Services\ReferensiService::LABEL_FIELD; @endphp
    <table>
        <tr><td>NIK</td><td>:</td><td>{{ $permohonan->nik }}</td></tr>
        <tr><td>No. WhatsApp</td><td>:</td><td>{{ $permohonan->no_wa }}</td></tr>
        <tr><td>Jenis Surat</td><td>:</td><td>{{ $permohonan->jenis_surat }}</td></tr>
        @foreach($permohonan->data_form ?? [] as $key => $val)
            <tr>
                <td>{{ $labelMap[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</td>
                <td>:</td>
                <td>{{ $val }}</td>
            </tr>
        @endforeach
    </table>

    <div class="note">
        Dokumen ini diterbitkan secara digital. Nomor Permohonan: {{ $permohonan->id }} | {{ $tanggalTerbit }}
    </div>
</body>
</html>
