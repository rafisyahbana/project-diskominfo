<x-app-layout>
    <x-slot name="header">Detail Permohonan #{{ substr($permohonan->id, 0, 8) }}</x-slot>

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="alert alert-success">
            ✓ {{ session('success') }}
        </div>
    @endif

    @if ($errors->has('status'))
        <div class="alert alert-error">
            ✗ {{ $errors->first('status') }}
        </div>
    @endif

    {{-- Back link --}}
    <div style="margin-bottom: 1.5rem;">
        <a href="{{ route('dashboard.permohonan.index') }}" class="link-primary" style="font-size: 0.875rem;">
            ← Kembali ke Daftar
        </a>
    </div>

    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Ringkasan Permohonan --}}
        <div class="dkm-card">
            <div class="dkm-card-header">
                <h3>Ringkasan Permohonan</h3>
                <span class="badge
                    {{ $permohonan->status === 'menunggu_verifikasi' ? 'badge-blue' : '' }}
                    {{ $permohonan->status === 'diproses' ? 'badge-yellow' : '' }}
                    {{ $permohonan->status === 'selesai' ? 'badge-green' : '' }}
                    {{ $permohonan->status === 'ditolak' ? 'badge-red' : '' }}">
                    {{ str_replace('_', ' ', $permohonan->status) }}
                </span>
            </div>
            <div class="dkm-card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>NIK</label>
                        <p>{{ $permohonan->nik }}</p>
                    </div>
                    <div class="detail-item">
                        <label>Nomor WhatsApp</label>
                        <p>{{ $permohonan->no_wa }}</p>
                    </div>
                    <div class="detail-item">
                        <label>Jenis Surat</label>
                        <p style="text-transform: capitalize;">{{ str_replace('_', ' ', $permohonan->jenis_surat) }}</p>
                    </div>
                    <div class="detail-item">
                        <label>Tanggal Pengajuan</label>
                        <p>{{ $permohonan->created_at->format('d M Y H:i:s') }}</p>
                    </div>
                    @if ($permohonan->petugas)
                        <div class="detail-item">
                            <label>Diproses Oleh</label>
                            <p>{{ $permohonan->petugas->nama }}</p>
                        </div>
                    @endif
                    @if ($permohonan->catatan_petugas)
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <label>Catatan Petugas</label>
                            <p style="background: #fefce8; padding: 0.75rem 1rem; border-radius: 8px; border-left: 3px solid #eab308;">{{ $permohonan->catatan_petugas }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Isi Formulir --}}
        <div class="dkm-card">
            <div class="dkm-card-header">
                <h3>Isi Formulir</h3>
            </div>
            <div class="dkm-card-body">
                <div class="detail-grid">
                    @foreach($permohonan->data_form as $key => $value)
                        <div class="detail-item">
                            <label>{{ $labelFields[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</label>
                            <p style="background: var(--neutral-bg); padding: 0.5rem 0.75rem; border-radius: 6px;">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Dokumen Pendukung --}}
        <div class="dkm-card">
            <div class="dkm-card-header">
                <h3>Dokumen Pendukung</h3>
            </div>
            <div class="dkm-card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem;">
                    @forelse($dokumen as $doc)
                        <div style="border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden;">
                            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid #f3f4f6;">
                                <span style="font-weight: 600; font-size: 0.875rem; text-transform: capitalize;">{{ str_replace('_', ' ', $doc->jenis_dokumen) }}</span>
                            </div>
                            <div style="background: var(--neutral-bg); height: 280px; display: flex; align-items: center; justify-content: center;">
                                <img src="{{ route('dashboard.dokumen.preview', $doc->id) }}" alt="{{ $doc->jenis_dokumen }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            </div>
                            <div style="padding: 0.75rem 1rem; text-align: center;">
                                <a href="{{ route('dashboard.dokumen.preview', $doc->id) }}" target="_blank" class="link-primary" style="font-size: 0.8125rem;">
                                    Lihat Penuh / Unduh
                                </a>
                            </div>
                        </div>
                    @empty
                        <p style="color: #9ca3af;">Tidak ada dokumen pendukung yang dilampirkan.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Tindakan: hanya tampil jika masih menunggu_verifikasi --}}
        @if ($permohonan->status === 'menunggu_verifikasi')
            <div class="dkm-card">
                <div class="dkm-card-header">
                    <h3>Tindakan</h3>
                </div>
                <div class="dkm-card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem;">

                        {{-- Panel Approve --}}
                        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 10px; padding: 1.5rem;">
                            <h4 style="font-weight: 600; color: #065f46; margin: 0 0 0.5rem;">Setujui Permohonan</h4>
                            <p style="font-size: 0.8125rem; color: #047857; margin: 0 0 1.25rem; line-height: 1.5;">Dokumen dan data form sudah sesuai. Lanjutkan ke proses pembuatan surat resmi.</p>
                            <form method="POST" action="{{ route('dashboard.permohonan.approve', $permohonan->id) }}" onsubmit="return confirm('Setujui permohonan ini?')">
                                @csrf
                                <button type="submit" id="btn-approve" class="btn btn-success" style="width: 100%; justify-content: center;">
                                    ✓ Setujui Permohonan
                                </button>
                            </form>
                        </div>

                        {{-- Panel Reject --}}
                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 1.5rem;">
                            <h4 style="font-weight: 600; color: #991b1b; margin: 0 0 0.5rem;">Tolak Permohonan</h4>
                            <p style="font-size: 0.8125rem; color: #b91c1c; margin: 0 0 1.25rem; line-height: 1.5;">Berkas tidak valid atau tidak lengkap. Berikan alasan penolakan kepada pemohon.</p>
                            <form method="POST" action="{{ route('dashboard.permohonan.reject', $permohonan->id) }}">
                                @csrf
                                <div style="margin-bottom: 0.75rem;">
                                    <label class="form-label" for="catatan_petugas" style="color: #991b1b;">
                                        Alasan Penolakan <span style="color: #dc2626;">*</span>
                                    </label>
                                    <textarea id="catatan_petugas" name="catatan_petugas" rows="3"
                                        class="form-textarea"
                                        style="border-color: #fca5a5;"
                                        placeholder="Wajib diisi. Minimal 10 karakter.">{{ old('catatan_petugas') }}</textarea>
                                    @error('catatan_petugas')
                                        <p style="margin-top: 0.3rem; font-size: 0.8rem; color: #dc2626;">{{ $message }}</p>
                                    @enderror
                                </div>
                                <button type="submit" id="btn-reject" class="btn btn-danger" style="width: 100%; justify-content: center;">
                                    ✗ Tolak Permohonan
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            </div>
        @endif

        {{-- Tombol Terbitkan: hanya untuk status diproses --}}
        @if ($permohonan->status === 'diproses')
            <div class="dkm-card">
                <div class="dkm-card-header">
                    <h3>Terbitkan Surat</h3>
                </div>
                <div class="dkm-card-body">
                    <p style="font-size: 0.875rem; color: #6b7280; margin: 0 0 1.25rem;">Permohonan ini telah diverifikasi. Klik tombol di bawah untuk membuat dan menerbitkan surat resmi dalam format PDF.</p>
                    <form method="POST" action="{{ route('dashboard.permohonan.terbitkan', $permohonan->id) }}"
                          onsubmit="return confirm('Terbitkan surat untuk permohonan ini? Tindakan ini tidak dapat dibatalkan.')">
                        @csrf
                        <button type="submit" id="btn-terbitkan" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                            📄 Terbitkan Surat Resmi
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Info Surat Selesai --}}
        @if ($permohonan->status === 'selesai')
            <div class="dkm-card" style="border-color: #a7f3d0; background: #f0fdf4;">
                <div class="dkm-card-header" style="border-bottom-color: #bbf7d0;">
                    <h3 style="color: #065f46;">✅ Surat Telah Diterbitkan</h3>
                </div>
                <div class="dkm-card-body">
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <div class="detail-item">
                            <label>Nomor Surat</label>
                            <p>{{ $permohonan->nomor_surat }}</p>
                        </div>
                        @php
                            $signedUrl = URL::temporarySignedRoute(
                                'surat.unduh',
                                now()->addDays(30),
                                ['permohonan' => $permohonan->id]
                            );
                        @endphp
                        <div class="detail-item">
                            <label>Link Unduh Warga <span style="text-transform: none; font-weight: 400; color: #9ca3af;">(berlaku 30 hari)</span></label>
                            <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem; align-items: center; flex-wrap: wrap;">
                                <input type="text" readonly value="{{ $signedUrl }}"
                                    class="form-input" style="flex: 1; font-size: 0.75rem; font-family: monospace; min-width: 200px; background: #f9fafb;">
                                <a href="{{ $signedUrl }}" target="_blank" class="btn btn-primary" style="white-space: nowrap; font-size: 0.8125rem; padding: 0.55rem 1rem;">
                                    Pratinjau / Unduh PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-app-layout>
