<x-app-layout>
    <x-slot name="header">Dashboard & Statistik Pelayanan</x-slot>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            text-decoration: none;
            color: inherit;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.09);
            border-color: #bfdbfe;
            text-decoration: none;
            color: inherit;
        }

        .stat-card-clickable-hint {
            font-size: 0.7rem;
            color: #bfdbfe;
            margin-top: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            opacity: 0;
            transition: opacity 0.18s ease;
        }

        .stat-card:hover .stat-card-clickable-hint {
            opacity: 1;
        }

        .stat-info h4 {
            margin: 0;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .stat-info .stat-value {
            margin: 0.4rem 0 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e3a5f;
            line-height: 1;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-icon svg {
            width: 22px;
            height: 22px;
            stroke-width: 1.75;
        }

        .stat-icon-blue { background: #eff6ff; color: #2563eb; }
        .stat-icon-yellow { background: #fefce8; color: #ca8a04; }
        .stat-icon-green { background: #ecfdf5; color: #059669; }
        .stat-icon-red { background: #fef2f2; color: #dc2626; }
        .stat-icon-indigo { background: #f0fdf4; color: #1e3a5f; }

        /* Highlight Top Request Banner */
        .top-request-banner {
            background: linear-gradient(135deg, #1e3a5f 0%, #2c6fbb 100%);
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(30, 58, 95, 0.15);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .top-request-tag {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            padding: 0.25rem 0.65rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 1024px) {
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        .ranking-bar-bg {
            background: #e5e7eb;
            border-radius: 999px;
            height: 8px;
            overflow: hidden;
            width: 100%;
        }

        .ranking-bar-fill {
            background: linear-gradient(90deg, #1e3a5f, #2c6fbb);
            height: 100%;
            border-radius: 999px;
            transition: width 0.5s ease-in-out;
        }
    </style>

    {{-- Filter Periode Tabs --}}
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
        <div class="tab-filters" style="margin-bottom: 0;">
            <a href="{{ route('dashboard.index', ['periode' => 'hari']) }}"
               class="tab-filter tab-filter-blue {{ $periode === 'hari' ? 'active' : '' }}">
                Hari Ini
            </a>
            <a href="{{ route('dashboard.index', ['periode' => 'minggu']) }}"
               class="tab-filter tab-filter-blue {{ $periode === 'minggu' ? 'active' : '' }}">
                Minggu Ini
            </a>
            <a href="{{ route('dashboard.index', ['periode' => 'bulan']) }}"
               class="tab-filter tab-filter-blue {{ $periode === 'bulan' ? 'active' : '' }}">
                Bulan Ini
            </a>
            <a href="{{ route('dashboard.index', ['periode' => 'tahun']) }}"
               class="tab-filter tab-filter-blue {{ $periode === 'tahun' ? 'active' : '' }}">
                Tahun Ini
            </a>
        </div>

        <div style="font-size: 0.875rem; color: #6b7280; font-weight: 500;">
            Periode: <strong style="color: #1e3a5f;">{{ $periodeLabel }}</strong>
        </div>
    </div>

    {{-- Highlight Top Requested Letter Card --}}
    @if ($topJenisSurat)
        <div class="top-request-banner">
            <div>
                <span class="top-request-tag">
                    Permohonan Paling Banyak
                </span>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin: 0.6rem 0 0.2rem; color: #ffffff;">
                    {{ $topJenisSurat['nama'] }}
                </h3>
                <p style="margin: 0; font-size: 0.875rem; color: rgba(255,255,255,0.8);">
                    Paling sering diajukan pada periode <strong>{{ $periodeLabel }}</strong>
                </p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 2.2rem; font-weight: 800; line-height: 1;">
                    {{ $topJenisSurat['total'] }} <span style="font-size: 1rem; font-weight: 400; opacity: 0.9;">Berkas</span>
                </div>
                <div style="font-size: 0.8125rem; color: rgba(255,255,255,0.8); margin-top: 0.25rem;">
                    {{ $topJenisSurat['persentase'] }}% dari total pengajuan
                </div>
            </div>
        </div>
    @endif

    {{-- Summary Cards Grid --}}
    <div class="stats-grid">
        {{-- Total Permohonan → ke semua permohonan --}}
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'semua']) }}" class="stat-card" title="Lihat semua permohonan">
            <div class="stat-info">
                <h4>Total Permohonan</h4>
                <div class="stat-value">{{ $totalPeriode }}</div>
                <span style="font-size: 0.75rem; color: #9ca3af;">(Semua waktu: {{ $totalSemua }})</span>
                <div class="stat-card-clickable-hint">Lihat daftar →</div>
            </div>
            <div class="stat-icon stat-icon-blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </a>

        {{-- Menunggu Verifikasi --}}
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'menunggu_verifikasi']) }}" class="stat-card" title="Lihat permohonan menunggu verifikasi">
            <div class="stat-info">
                <h4>Menunggu Verifikasi</h4>
                <div class="stat-value" style="color: #2563eb;">{{ $menungguVerifikasi }}</div>
                <span style="font-size: 0.75rem; color: #9ca3af;">Perlu ditinjau</span>
                <div class="stat-card-clickable-hint">Lihat daftar →</div>
            </div>
            <div class="stat-icon stat-icon-blue">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </a>

        {{-- Sedang Diproses --}}
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'diproses']) }}" class="stat-card" title="Lihat permohonan sedang diproses">
            <div class="stat-info">
                <h4>Sedang Diproses</h4>
                <div class="stat-value" style="color: #d97706;">{{ $diproses }}</div>
                <span style="font-size: 0.75rem; color: #9ca3af;">Siap terbitkan</span>
                <div class="stat-card-clickable-hint">Lihat daftar →</div>
            </div>
            <div class="stat-icon stat-icon-yellow">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
            </div>
        </a>

        {{-- Selesai Diterbitkan --}}
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'selesai']) }}" class="stat-card" title="Lihat permohonan selesai diterbitkan">
            <div class="stat-info">
                <h4>Selesai Diterbitkan</h4>
                <div class="stat-value" style="color: #059669;">{{ $selesai }}</div>
                <span style="font-size: 0.75rem; color: #9ca3af;">Surat terbit</span>
                <div class="stat-card-clickable-hint">Lihat daftar →</div>
            </div>
            <div class="stat-icon stat-icon-green">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </a>

        {{-- Ditolak --}}
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'ditolak']) }}" class="stat-card" title="Lihat permohonan ditolak">
            <div class="stat-info">
                <h4>Ditolak</h4>
                <div class="stat-value" style="color: #dc2626;">{{ $ditolak }}</div>
                <span style="font-size: 0.75rem; color: #9ca3af;">Berkas tidak valid</span>
                <div class="stat-card-clickable-hint">Lihat daftar →</div>
            </div>
            <div class="stat-icon stat-icon-red">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </a>
    </div>

    {{-- Charts Section --}}
    <div class="charts-row">
        {{-- Trend Line / Bar Chart --}}
        <div class="dkm-card">
            <div class="dkm-card-header">
                <h3>Tren Permintaan Surat ({{ $periodeLabel }})</h3>
                <span style="font-size: 0.8125rem; color: #6b7280;">Berdasarkan Waktu Pengajuan</span>
            </div>
            <div class="dkm-card-body">
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Distribution Doughnut Chart --}}
        <div class="dkm-card">
            <div class="dkm-card-header">
                <h3>Distribusi Jenis Surat</h3>
                <span style="font-size: 0.8125rem; color: #6b7280;">Proporsi Pengajuan</span>
            </div>
            <div class="dkm-card-body">
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="distributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    {{-- 14 Jenis Surat Ranking Table --}}
    <div class="dkm-card">
        <div class="dkm-card-header">
            <div>
                <h3>Daftar Permintaan Berdasarkan 14 Jenis Surat</h3>
                <p style="margin: 0.25rem 0 0; font-size: 0.8125rem; color: #6b7280;">
                    Diurutkan dari pengajuan surat yang paling banyak diminta oleh warga
                </p>
            </div>
            <a href="{{ route('dashboard.permohonan.index') }}" class="btn btn-primary" style="font-size: 0.8125rem; padding: 0.5rem 1rem;">
                Lihat Semua Berkas →
            </a>
        </div>
        <div class="dkm-card-body" style="padding: 0;">
            <table class="dkm-table">
                <thead>
                    <tr>
                        <th style="width: 60px; text-align: center;">No</th>
                        <th>Jenis Surat</th>
                        <th style="width: 250px;">Distribusi Volume</th>
                        <th style="width: 120px; text-align: right;">Jumlah</th>
                        <th style="width: 100px; text-align: right;">Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($statJenisSurat as $idx => $item)
                        <tr>
                            <td style="text-align: center; font-weight: 600; color: {{ $idx < 3 && $item['total'] > 0 ? '#2563eb' : '#9ca3af' }};">
                                {{ $idx + 1 }}
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #1e3a5f;">
                                    {{ $item['nama'] }}
                                </div>
                                <span style="font-size: 0.75rem; color: #9ca3af; font-family: monospace;">
                                    Kode: {{ $item['key'] }}
                                </span>
                            </td>
                            <td>
                                <div class="ranking-bar-bg">
                                    <div class="ranking-bar-fill" style="width: {{ $item['persentase'] }}%;"></div>
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 700; color: #1e3a5f;">
                                {{ $item['total'] }} <span style="font-weight: 400; font-size: 0.75rem; color: #6b7280;">berkas</span>
                            </td>
                            <td style="text-align: right;">
                                <span class="badge {{ $item['total'] > 0 ? 'badge-blue' : 'badge-yellow' }}">
                                    {{ $item['persentase'] }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Script Inisialisasi Chart.js --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Palette Biru Diskominfo
            const primaryBlue = '#1e3a5f';
            const accentBlue = '#2c6fbb';
            const lightBlue = 'rgba(44, 111, 187, 0.15)';

            // 1. Chart Tren Permintaan
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: @json($chartLabels),
                    datasets: [{
                        label: 'Permintaan Surat',
                        data: @json($chartValues),
                        borderColor: accentBlue,
                        backgroundColor: lightBlue,
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: primaryBlue,
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: primaryBlue,
                            padding: 10,
                            titleFont: { size: 12, family: 'Inter' },
                            bodyFont: { size: 13, family: 'Inter' }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, stepSize: 1, color: '#6b7280' },
                            grid: { color: '#f3f4f6' }
                        },
                        x: {
                            ticks: { color: '#6b7280', maxRotation: 45, minRotation: 0 },
                            grid: { display: false }
                        }
                    }
                }
            });

            // 2. Chart Distribusi Jenis Surat
            const distCtx = document.getElementById('distributionChart').getContext('2d');
            const distLabels = @json($distribusiLabels);
            const distValues = @json($distribusiValues);

            const colors = [
                '#1e3a5f', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd',
                '#059669', '#10b981', '#34d399', '#f59e0b', '#fbbf24',
                '#8b5cf6', '#a78bfa', '#ec4899', '#9ca3af'
            ];

            new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: distLabels,
                    datasets: [{
                        data: distValues,
                        backgroundColor: colors.slice(0, distLabels.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 12,
                                font: { size: 11, family: 'Inter' }
                            }
                        },
                        tooltip: {
                            backgroundColor: primaryBlue,
                            padding: 10
                        }
                    },
                    cutout: '65%'
                }
            });
        });
    </script>
</x-app-layout>
