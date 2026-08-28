<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kesalahan</title>
    <style>
        body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 20px; color: #333; text-align: center; }
        .container { max-width: 500px; margin: 50px auto; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); border-top: 5px solid #dc2626; }
        .icon { font-size: 60px; color: #dc2626; margin-bottom: 20px; }
        h2 { margin-top: 0; color: #1f2937; }
        p { color: #4b5563; line-height: 1.5; font-size: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">!</div>
        <h2>Oops! Terjadi Kesalahan</h2>
        <p>{{ $message }}</p>
    </div>
</body>
</html>
