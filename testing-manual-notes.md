# Panduan Testing Manual — Sistem Layanan Surat Diskominfo

## Prasyarat

Pastikan server sudah berjalan:
```bash
php artisan serve
# Atau untuk production-like:
php artisan serve --host=0.0.0.0 --port=8000
```

---

## Kredensial & Data Testing

### Akun Petugas Dashboard Admin
| Field    | Nilai                    |
|----------|--------------------------|
| URL Login | http://localhost:8000/dashboard/login |
| Email    | `admin@diskominfo.local` |
| Password | `password123`            |

> ⚠️ **HARUS DIGANTI SEBELUM PRODUCTION**

### Data Warga Dummy (untuk simulasi WhatsApp)
| No | NIK                | Nama           | No WA (format intl) |
|----|-------------------|----------------|---------------------|
| 1  | `1111111111111111` | Budi Santoso  | `628111111111`      |
| 2  | `2222222222222222` | Siti Rahayu   | `628222222222`      |
| 3  | `3333333333333333` | Ahmad Fauzi   | `628333333333`      |

### API Key (untuk header X-Agent-Api-Key)
```
openclaw-dev-key-Sn8sKY3SQLe22WxxwQlef74KB2oAjmAkX4KZDqg
```
(Nilai dari `.env` → `AGENT_API_KEY`)

---

## Langkah 1: Alur Percakapan via Postman

Import `testing-manual.postman_collection.json` ke Postman, buat Environment dengan:
- `base_url` = `http://localhost:8000`  
- `api_key`  = (nilai di atas)  
- `no_wa`    = `628111111111`

Jalankan request **secara berurutan** (1 → 7).

### 🔍 Cara Cek OTP (Request #3)

Setelah kirim NIK, OTP dikirim via "WhatsApp" stub yang hanya di-log.
Buka file log:
```
storage/logs/laravel.log
```
Cari baris:
```
[StubLlmResponder] atau OTP ... adalah: XXXXXX
```

Atau gunakan Tinker:
```bash
php artisan tinker
>>> App\Models\SesiVerifikasi::latest()->first()
# Lihat kolom otp_hash — tapi hash tidak bisa dibaca langsung.
# Cek log lebih mudah.
```

> **Alternatif lebih mudah**: Pakai endpoint debug (jika tersedia) atau
> lihat di `storage/logs/laravel.log` baris yang mengandung "OTP".

---

## Langkah 2: Login Dashboard Admin

1. Buka browser → `http://localhost:8000/dashboard/login`
2. Login dengan:
   - Email: `admin@diskominfo.local`
   - Password: `password123`
3. Setelah login, akan redirect ke `/dashboard/permohonan`

---

## Langkah 3: Proses Permohonan di Dashboard

### 3.1 Lihat Daftar Permohonan
- Halaman default menampilkan permohonan berstatus **"menunggu_verifikasi"** (FIFO).
- Permohonan dari warga dummy seharusnya muncul di sini.

### 3.2 Buka Detail & Approve
1. Klik **"Detail"** pada permohonan yang ingin diproses.
2. Periksa data form dan dokumen yang dilampirkan.
3. Klik tombol **"✓ Setujui Permohonan"** → status berubah ke `diproses`.

### 3.3 Terbitkan Surat (PDF)
1. Dari halaman detail yang sama (status sudah `diproses`).
2. Klik tombol **"📄 Terbitkan Surat Resmi"**.
3. Konfirmasi dialog.
4. Sistem akan:
   - Generate nomor surat (format: `SURAT-YYYYMMDD-0001`)
   - Render PDF via dompdf
   - Simpan ke `storage/app/private/surat/{id_permohonan}.pdf`
   - Kirim "notifikasi WhatsApp" (stub: simpan ke DB)
5. Halaman detail akan menampilkan **link signed URL** untuk unduh PDF.

### 3.4 Alternatif: Tolak Permohonan
1. Dari halaman detail (status `menunggu_verifikasi`).
2. Isi textarea **"Alasan Penolakan"** (minimal 10 karakter).
3. Klik **"✗ Tolak Permohonan"**.
4. Status berubah ke `ditolak`, notifikasi terkirim ke warga.

---

## Langkah 4: Verifikasi via Tinker

### Cek Seluruh Permohonan
```bash
php artisan tinker
>>> App\Models\Permohonan::all(['id','nik','jenis_surat','status','nomor_surat'])
```

### Cek Notifikasi yang "Terkirim"
```bash
>>> App\Models\NotifikasiTerkirim::all()

# Atau tampilkan pesan lebih rapi:
>>> App\Models\NotifikasiTerkirim::latest()->get()->each(fn($n) => dump($n->no_wa, $n->pesan))
```

### Cek State Percakapan Warga
```bash
>>> App\Models\PercakapanState::where('no_wa', '628111111111')->first()
```

### Cek Dokumen yang Diupload
```bash
>>> App\Models\DokumenPermohonan::where('no_wa', '628111111111')->get()
```

### Cek Semua Sesi Verifikasi
```bash
>>> App\Models\SesiVerifikasi::where('no_wa', '628111111111')->latest()->first()
```

### Cek Log Akses Agent
```bash
>>> App\Models\LogAksesAgent::where('no_wa', '628111111111')
...         ->orderByDesc('created_at')
...         ->take(10)
...         ->get(['tool_dipanggil','hasil','created_at'])
```

---

## Langkah 5: Unduh PDF Surat (sebagai Warga)

Setelah surat diterbitkan, copy **Link Unduh Warga** dari halaman detail dashboard.
Link berbentuk:
```
http://localhost:8000/surat/{uuid}/unduh?expires=...&signature=...
```

Buka link ini di browser atau gunakan Postman **tanpa header API key** 
(route ini publik, dilindungi signature saja, bukan auth).

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| OTP tidak ditemukan di log | Cek `storage/logs/laravel.log`, scroll ke bawah. Atau jalankan `php artisan log:tail` |
| 401 di API | Pastikan header `X-Agent-Api-Key` sudah benar sesuai `.env AGENT_API_KEY` |
| 403 di link unduh | Link sudah kadaluwarsa (default 30 hari) atau signature dipalsukan. Buat ulang dari dashboard |
| Dashboard redirect loop | Pastikan session driver berjalan: `php artisan migrate` (tabel sessions harus ada) |
| PDF kosong/error | Pastikan dompdf terinstall: `composer show barryvdh/laravel-dompdf` |

---

## Reset Data Testing

Untuk memulai ulang dari awal:
```bash
php artisan migrate:fresh
php artisan db:seed --class=WargaTestSeeder
php artisan db:seed --class=PetugasSeeder
```
