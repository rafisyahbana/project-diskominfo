<x-app-layout>
    <x-slot name="header">Daftar Permohonan</x-slot>

    <style>
        .search-bar-wrap {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }

        .search-input-wrap {
            position: relative;
            flex: 1;
            max-width: 420px;
        }

        .search-input-wrap svg {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            stroke: #9ca3af;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 0.55rem 1rem 0.55rem 2.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #1e3a5f;
            background: #f3f4f6;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            outline: none;
        }

        .search-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-input::placeholder {
            color: #9ca3af;
        }

        .btn-search {
            padding: 0.55rem 1.25rem;
            background: #1e3a5f;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-search:hover {
            background: #2c6fbb;
        }

        .btn-reset {
            padding: 0.55rem 1rem;
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background 0.15s ease;
        }

        .btn-reset:hover {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
        }

        .search-result-info {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-bottom: 0.75rem;
        }

        .search-result-info strong {
            color: #1e3a5f;
        }
    </style>

    {{-- Tab Filters — sertakan keyword search agar tidak hilang saat ganti tab --}}
    <div class="tab-filters">
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'semua', 'search' => $search]) }}"
           class="tab-filter tab-filter-blue {{ $status === 'semua' ? 'active' : '' }}">
            Semua
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'menunggu_verifikasi', 'search' => $search]) }}"
           class="tab-filter tab-filter-blue {{ $status === 'menunggu_verifikasi' ? 'active' : '' }}">
            Menunggu Verifikasi
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'diproses', 'search' => $search]) }}"
           class="tab-filter tab-filter-yellow {{ $status === 'diproses' ? 'active' : '' }}">
            Diproses
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'selesai', 'search' => $search]) }}"
           class="tab-filter tab-filter-green {{ $status === 'selesai' ? 'active' : '' }}">
            Selesai
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'ditolak', 'search' => $search]) }}"
           class="tab-filter tab-filter-red {{ $status === 'ditolak' ? 'active' : '' }}">
            Ditolak
        </a>
    </div>

    {{-- Table Card --}}
    <div class="dkm-card">

        {{-- Card Header: Judul kiri, Search kanan --}}
        <div class="dkm-card-header">
            <h3>Daftar Permohonan</h3>
            <form method="GET" action="{{ route('dashboard.permohonan.index') }}" class="search-bar-wrap" style="margin-bottom: 0;">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="search-input-wrap">
                    <svg fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                    </svg>
                    <input
                        id="search-input"
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        class="search-input"
                        placeholder="Cari nama, NIK, atau No. WA..."
                        autocomplete="off"
                    >
                </div>
                <button type="submit" class="btn-search">Cari</button>
                @if ($search)
                    <a href="{{ route('dashboard.permohonan.index', ['status' => $status]) }}" class="btn-reset">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        {{-- Info hasil pencarian --}}
        @if ($search)
            <div style="padding: 0.5rem 1.5rem; font-size: 0.8125rem; color: #6b7280; background: #f9fafb; border-bottom: 1px solid #f3f4f6;">
                Hasil pencarian: <strong style="color: #1e3a5f;">"{{ $search }}"</strong>
                &mdash; {{ $permohonan->total() }} data ditemukan
            </div>
        @endif

        <div class="dkm-card-body" style="padding: 0;">
            <table class="dkm-table">


                <thead>
                    <tr>
                        <th>Tgl / Waktu</th>
                        <th>Nama / NIK / No. WA</th>
                        <th>Jenis Surat</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($permohonan as $p)
                        <tr>
                            <td>{{ $p->created_at->format('d M Y H:i') }}</td>
                            <td>
                                @if ($p->nama_warga)
                                    <span style="font-weight: 600; color: #1e3a5f;">{{ $p->nama_warga }}</span><br>
                                @endif
                                <span style="font-size: 0.8rem; color: #374151;">{{ $p->nik }}</span><br>
                                <span style="color: #9ca3af; font-size: 0.8rem;">{{ $p->no_wa }}</span>
                            </td>
                            <td style="text-transform: capitalize;">{{ str_replace('_', ' ', $p->jenis_surat) }}</td>
                            <td>
                                <span class="badge
                                    {{ $p->status === 'menunggu_verifikasi' ? 'badge-blue' : '' }}
                                    {{ $p->status === 'diproses' ? 'badge-yellow' : '' }}
                                    {{ $p->status === 'selesai' ? 'badge-green' : '' }}
                                    {{ $p->status === 'ditolak' ? 'badge-red' : '' }}">
                                    {{ str_replace('_', ' ', $p->status) }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('dashboard.permohonan.show', $p->id) }}" class="link-primary" style="font-size: 0.8125rem;">
                                    Detail →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 3rem 1rem; color: #9ca3af;">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#d1d5db" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 0.75rem;">
                                    <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="12" y1="18" x2="12" y2="12"/>
                                    <line x1="9" y1="15" x2="15" y2="15"/>
                                </svg>
                                <br>
                                @if ($search)
                                    Tidak ada permohonan yang cocok dengan pencarian <strong>"{{ $search }}"</strong>.
                                @else
                                    Tidak ada permohonan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if ($permohonan->hasPages())
        <div style="margin-top: 1.25rem;">
            {{ $permohonan->links() }}
        </div>
    @endif
</x-app-layout>

