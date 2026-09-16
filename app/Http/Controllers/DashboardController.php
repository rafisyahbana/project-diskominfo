<?php

namespace App\Http\Controllers;

use App\Contracts\WhatsAppNotifier;
use App\Models\Permohonan;
use App\Models\DokumenPermohonan;
use App\Services\ReferensiService;
use App\Services\SuratPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class DashboardController extends Controller
{
    /**
     * 14 Jenis Surat Resmi Diskominfo
     */
    public const DAFTAR_JENIS_SURAT = [
        'domisili'       => 'Surat Keterangan Domisili',
        'usaha'          => 'Surat Keterangan Usaha',
        'sktm'           => 'Surat Keterangan Tidak Mampu',
        'skck'           => 'Surat Pengantar SKCK',
        'belum_menikah'  => 'Surat Keterangan Belum Pernah Menikah',
        'kelahiran'      => 'Surat Keterangan Kelahiran',
        'kematian'       => 'Surat Keterangan Kematian',
        'pindah'         => 'Surat Pengantar Pindah',
        'penghasilan'    => 'Surat Keterangan Penghasilan',
        'tanah'          => 'Surat Keterangan Tanah',
        'ahli_waris'     => 'Surat Keterangan Ahli Waris',
        'beda_nama'      => 'Surat Keterangan Beda Nama',
        'nikah'          => 'Surat Pengantar Nikah Model N1-N4',
        'lainnya'        => 'Lainnya',
    ];

    public function dashboard(Request $request)
    {
        $periode = $request->query('periode', 'bulan');
        if (!in_array($periode, ['hari', 'minggu', 'bulan', 'tahun'])) {
            $periode = 'bulan';
        }

        // Base query periode
        $now = now();
        $queryPeriode = Permohonan::query();

        switch ($periode) {
            case 'hari':
                $queryPeriode->whereDate('created_at', $now->toDateString());
                $periodeLabel = 'Hari Ini (' . $now->translatedFormat('d F Y') . ')';
                break;
            case 'minggu':
                $startOfWeek = $now->copy()->startOfWeek();
                $endOfWeek   = $now->copy()->endOfWeek();
                $queryPeriode->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
                $periodeLabel = 'Minggu Ini (' . $startOfWeek->translatedFormat('d M') . ' - ' . $endOfWeek->translatedFormat('d M Y') . ')';
                break;
            case 'tahun':
                $queryPeriode->whereYear('created_at', $now->year);
                $periodeLabel = 'Tahun ' . $now->year;
                break;
            case 'bulan':
            default:
                $queryPeriode->whereYear('created_at', $now->year)
                             ->whereMonth('created_at', $now->month);
                $periodeLabel = 'Bulan ' . $now->translatedFormat('F Y');
                break;
        }

        // Summary Cards
        $totalSemua = Permohonan::count();
        $totalPeriode = (clone $queryPeriode)->count();
        $menungguVerifikasi = Permohonan::where('status', 'menunggu_verifikasi')->count();
        $diproses = Permohonan::where('status', 'diproses')->count();
        $selesai = Permohonan::where('status', 'selesai')->count();
        $ditolak = Permohonan::where('status', 'ditolak')->count();

        // Rekap Jenis Surat dalam periode terpilih
        $permohonanByJenis = (clone $queryPeriode)
            ->selectRaw('jenis_surat, count(*) as total')
            ->groupBy('jenis_surat')
            ->pluck('total', 'jenis_surat')
            ->toArray();

        // Susun 14 jenis surat lengkap dengan hitungannya
        $statJenisSurat = [];
        foreach (self::DAFTAR_JENIS_SURAT as $key => $nama) {
            $count = $permohonanByJenis[$key] ?? 0;
            // Jika ada surat lama bernama 'pengantar', satukan ke 'lainnya' atau tetap hitung
            if ($key === 'lainnya' && isset($permohonanByJenis['pengantar'])) {
                $count += $permohonanByJenis['pengantar'];
            }
            $statJenisSurat[] = [
                'key'        => $key,
                'nama'       => $nama,
                'total'      => $count,
                'persentase' => $totalPeriode > 0 ? round(($count / $totalPeriode) * 100, 1) : 0,
            ];
        }

        // Urutkan dari yang terbanyak
        usort($statJenisSurat, fn($a, $b) => $b['total'] <=> $a['total']);

        // Temukan jenis surat terpopuler (Top Requested)
        $topJenisSurat = $statJenisSurat[0]['total'] > 0 ? $statJenisSurat[0] : null;

        // Data Grafik Tren berdasarkan Periode
        $chartLabels = [];
        $chartValues = [];

        if ($periode === 'hari') {
            // Breakdown per jam (00:00 - 23:00)
            for ($h = 0; $h <= 23; $h++) {
                $hourLabel = sprintf('%02d:00', $h);
                $chartLabels[] = $hourLabel;
                $countHour = (clone $queryPeriode)
                    ->whereTime('created_at', '>=', sprintf('%02d:00:00', $h))
                    ->whereTime('created_at', '<=', sprintf('%02d:59:59', $h))
                    ->count();
                $chartValues[] = $countHour;
            }
        } elseif ($periode === 'minggu') {
            // 7 hari Senin s/d Minggu
            $startOfWeek = $now->copy()->startOfWeek();
            for ($d = 0; $d < 7; $d++) {
                $dayDate = $startOfWeek->copy()->addDays($d);
                $chartLabels[] = $dayDate->translatedFormat('l, d M');
                $countDay = Permohonan::whereDate('created_at', $dayDate->toDateString())->count();
                $chartValues[] = $countDay;
            }
        } elseif ($periode === 'tahun') {
            // 12 Bulan (Jan - Des)
            for ($m = 1; $m <= 12; $m++) {
                $monthDate = \Carbon\Carbon::createFromDate($now->year, $m, 1);
                $chartLabels[] = $monthDate->translatedFormat('F');
                $countMonth = Permohonan::whereYear('created_at', $now->year)
                    ->whereMonth('created_at', $m)
                    ->count();
                $chartValues[] = $countMonth;
            }
        } else {
            // Bulan ini: per tanggal (1 sampai jumlah hari di bulan ini)
            $daysInMonth = $now->daysInMonth;
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dayDate = \Carbon\Carbon::createFromDate($now->year, $now->month, $day);
                $chartLabels[] = $dayDate->format('d M');
                $countDay = Permohonan::whereDate('created_at', $dayDate->toDateString())->count();
                $chartValues[] = $countDay;
            }
        }

        // Data Grafik Distribusi (Doughnut/Bar) untuk Top Jenis Surat
        $distribusiLabels = [];
        $distribusiValues = [];
        foreach ($statJenisSurat as $item) {
            if ($item['total'] > 0 || count($distribusiLabels) < 5) {
                $distribusiLabels[] = $item['nama'];
                $distribusiValues[] = $item['total'];
            }
        }

        return view('dashboard.index', compact(
            'periode',
            'periodeLabel',
            'totalSemua',
            'totalPeriode',
            'menungguVerifikasi',
            'diproses',
            'selesai',
            'ditolak',
            'statJenisSurat',
            'topJenisSurat',
            'chartLabels',
            'chartValues',
            'distribusiLabels',
            'distribusiValues'
        ));
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'semua');
        $validStatus = ['semua', 'menunggu_verifikasi', 'diproses', 'selesai', 'ditolak'];
        $search = trim($request->query('search', ''));

        if (!in_array($status, $validStatus)) {
            $status = 'semua';
        }

        $query = Permohonan::query()
            ->leftJoin('warga', 'permohonan.nik', '=', 'warga.nik')
            ->select('permohonan.*', 'warga.nama as nama_warga');

        if ($status !== 'semua') {
            $query->where('permohonan.status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('permohonan.nik', 'like', "%{$search}%")
                  ->orWhere('permohonan.no_wa', 'like', "%{$search}%")
                  ->orWhere('warga.nama', 'like', "%{$search}%");
            });
        }

        $permohonan = $query->orderBy('permohonan.created_at', 'desc')->paginate(15)->withQueryString();

        return view('dashboard.permohonan.index', compact('permohonan', 'status', 'search'));
    }

    public function show(string $id)
    {
        $permohonan = Permohonan::with('petugas')->findOrFail($id);
        $dokumen    = DokumenPermohonan::where('id_permohonan', $id)->get();
        $labelFields = ReferensiService::LABEL_FIELD;

        return view('dashboard.permohonan.show', compact('permohonan', 'dokumen', 'labelFields'));
    }

    public function approve(string $id)
    {
        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'menunggu_verifikasi') {
            return back()->withErrors([
                'status' => "Permohonan tidak dapat disetujui karena statusnya sudah '{$permohonan->status}'."
                         . " Kemungkinan sudah diproses oleh petugas lain.",
            ]);
        }

        $permohonan->update([
            'status'        => 'diproses',
            'diproses_oleh' => Auth::guard('petugas')->id(),
        ]);

        // ⚠ Approve ke 'diproses' adalah status antara (bukan final) — TIDAK kirim notif ke warga.

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', 'Permohonan berhasil disetujui dan sedang diproses.');
    }

    public function reject(Request $request, string $id)
    {
        $request->validate([
            'catatan_petugas' => ['required', 'string', 'min:10'],
        ], [
            'catatan_petugas.required' => 'Alasan penolakan wajib diisi.',
            'catatan_petugas.min'      => 'Alasan penolakan minimal 10 karakter.',
        ]);

        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'menunggu_verifikasi') {
            return back()->withErrors([
                'status' => "Permohonan tidak dapat ditolak karena statusnya sudah '{$permohonan->status}'."
                          . " Kemungkinan sudah diproses oleh petugas lain.",
            ])->withInput();
        }

        $permohonan->update([
            'status'          => 'ditolak',
            'catatan_petugas' => $request->catatan_petugas,
            'diproses_oleh'   => Auth::guard('petugas')->id(),
        ]);

        // Notifikasi ke warga: permohonan ditolak
        $pesan = $this->susunPesanDitolak($permohonan->fresh());
        $this->kirimNotifikasi($permohonan->no_wa, $pesan, $permohonan->id);

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', 'Permohonan berhasil ditolak.');
    }

    public function previewDokumen(string $id)
    {
        $dokumen = DokumenPermohonan::findOrFail($id);

        if (!Storage::disk('local')->exists($dokumen->path_file)) {
            abort(404, 'Dokumen tidak ditemukan di storage.');
        }

        return Storage::disk('local')->response($dokumen->path_file);
    }

    public function terbitkan(string $id)
    {
        $permohonan = Permohonan::findOrFail($id);

        if ($permohonan->status !== 'diproses') {
            return back()->withErrors([
                'status' => "Surat hanya bisa diterbitkan untuk permohonan berstatus 'diproses'."
                          . " Status saat ini: '{$permohonan->status}'.",
            ]);
        }

        $service  = new SuratPdfService();
        $pathFile = $service->generate($permohonan);

        $permohonan->update([
            'status'         => 'selesai',
            'file_surat_url' => $pathFile,
        ]);

        $permohonan->refresh();

        // Bangun signed URL unduh warga (30 hari)
        $linkUnduh = URL::temporarySignedRoute(
            'surat.unduh',
            now()->addDays(30),
            ['permohonan' => $permohonan->id]
        );

        // Notifikasi ke warga: surat selesai
        $pesan = $this->susunPesanSelesai($permohonan, $linkUnduh);
        $this->kirimNotifikasi($permohonan->no_wa, $pesan, $permohonan->id);

        return redirect()
            ->route('dashboard.permohonan.show', $id)
            ->with('success', "Surat berhasil diterbitkan dengan nomor {$permohonan->nomor_surat}.");
    }

    /**
     * Route publik (signed URL, 30 hari) untuk warga mengunduh surat PDF.
     * Tidak perlu login petugas.
     */
    public function unduhSurat(Permohonan $permohonan)
    {
        if (!$permohonan->file_surat_url || !Storage::disk('local')->exists($permohonan->file_surat_url)) {
            abort(404, 'File surat tidak ditemukan.');
        }

        $filename = "surat-{$permohonan->nomor_surat}.pdf";
        return Storage::disk('local')->download($permohonan->file_surat_url, $filename);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function susunPesanSelesai(Permohonan $permohonan, string $linkUnduh): string
    {
        $jenis = ucwords(str_replace('_', ' ', $permohonan->jenis_surat));

        $pesan  = "✅ *Surat Anda Telah Diterbitkan*\n\n";
        $pesan .= "📄 Jenis Surat : {$jenis}\n";
        $pesan .= "🔖 Nomor Surat : {$permohonan->nomor_surat}\n";
        $pesan .= "📅 Tanggal     : " . now()->format('d M Y') . "\n\n";
        $pesan .= "Silakan unduh surat Anda melalui link berikut (berlaku 30 hari):\n";
        $pesan .= $linkUnduh . "\n\n";
        $pesan .= "Terima kasih telah menggunakan layanan kami.";

        return $pesan;
    }

    private function susunPesanDitolak(Permohonan $permohonan): string
    {
        $jenis = ucwords(str_replace('_', ' ', $permohonan->jenis_surat));

        $pesan  = "❌ *Permohonan Surat Ditolak*\n\n";
        $pesan .= "📄 Jenis Surat : {$jenis}\n";
        $pesan .= "📅 Tanggal     : " . now()->format('d M Y') . "\n\n";
        $pesan .= "Alasan penolakan:\n_{$permohonan->catatan_petugas}_\n\n";
        $pesan .= "Jika Anda memiliki pertanyaan, silakan hubungi petugas Diskominfo.";

        return $pesan;
    }

    /**
     * Kirim notifikasi via WhatsAppNotifier.
     * Kegagalan kirim TIDAK membatalkan proses — hanya di-log sebagai warning.
     */
    private function kirimNotifikasi(string $noWa, string $pesan, ?string $idPermohonan = null): void
    {
        try {
            $notifier = app(WhatsAppNotifier::class);
            $berhasil = $notifier->kirim($noWa, $pesan);

            if (!$berhasil) {
                Log::warning('[DashboardController] Notifikasi gagal terkirim', [
                    'no_wa'          => $noWa,
                    'id_permohonan'  => $idPermohonan,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[DashboardController] Exception saat kirim notifikasi', [
                'no_wa'          => $noWa,
                'id_permohonan'  => $idPermohonan,
                'error'          => $e->getMessage(),
            ]);
        }
    }
}
