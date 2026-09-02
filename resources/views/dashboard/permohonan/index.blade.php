<x-app-layout>
    <x-slot name="header">Daftar Permohonan</x-slot>

    {{-- Tab Filters --}}
    <div class="tab-filters">
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'menunggu_verifikasi']) }}"
           class="tab-filter tab-filter-blue {{ $status === 'menunggu_verifikasi' ? 'active' : '' }}">
            Menunggu Verifikasi
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'diproses']) }}"
           class="tab-filter tab-filter-yellow {{ $status === 'diproses' ? 'active' : '' }}">
            Diproses
        </a>
        <a href="{{ route('dashboard.permohonan.index', ['status' => 'selesai']) }}"
           class="tab-filter tab-filter-green {{ $status === 'selesai' ? 'active' : '' }}">
            Selesai
        </a>
    </div>

    {{-- Table Card --}}
    <div class="dkm-card">
        <div class="dkm-card-body" style="padding: 0;">
            <table class="dkm-table">
                <thead>
                    <tr>
                        <th>Tgl / Waktu</th>
                        <th>NIK / No. WA</th>
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
                                <span style="font-weight: 600;">{{ $p->nik }}</span><br>
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
                                <br>Tidak ada permohonan.
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
