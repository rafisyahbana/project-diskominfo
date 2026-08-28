<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unggah {{ $labelDokumen }}</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 500px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #1f2937; }
        p { color: #4b5563; line-height: 1.5; }
        .form-group { margin-top: 20px; }
        label { font-weight: bold; display: block; margin-bottom: 10px; }
        input[type=file] { display: block; width: 100%; padding: 10px; border: 1px dashed #cbd5e1; border-radius: 5px; background: #f8fafc; }
        .btn { display: inline-block; width: 100%; padding: 12px; background: #2563eb; color: #fff; border: none; border-radius: 5px; font-size: 16px; font-weight: bold; margin-top: 20px; cursor: pointer; }
        .btn:hover { background: #1d4ed8; }
        .error { color: #dc2626; background: #fef2f2; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Unggah Dokumen</h2>
        <p>Silakan unggah foto <strong>{{ $labelDokumen }}</strong> Anda untuk melanjutkan permohonan.</p>
        
        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ $postUrl }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="file">Pilih Foto / Ambil dari Kamera:</label>
                <input type="file" id="file" name="file" accept="image/*" required>
            </div>
            <button type="submit" class="btn">Unggah Dokumen</button>
        </form>
    </div>
</body>
</html>
