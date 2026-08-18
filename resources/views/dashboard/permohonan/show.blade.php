<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Detail Permohonan #{{ substr($permohonan->id, 0, 8) }}
            </h2>
            <a href="{{ route('dashboard.permohonan.index') }}" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke Daftar</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Flash messages --}}
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->has('status'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                    {{ $errors->first('status') }}
                </div>
            @endif

            {{-- Status & Pemroses --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium border-b pb-2 mb-4">Ringkasan Permohonan</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">NIK</p>
                            <p class="font-medium">{{ $permohonan->nik }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Nomor WhatsApp</p>
                            <p class="font-medium">{{ $permohonan->no_wa }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Jenis Surat</p>
                            <p class="font-medium capitalize">{{ str_replace('_', ' ', $permohonan->jenis_surat) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Status</p>
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                {{ $permohonan->status === 'menunggu_verifikasi' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $permohonan->status === 'diproses' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $permohonan->status === 'selesai' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $permohonan->status === 'ditolak' ? 'bg-red-100 text-red-800' : '' }}">
                                {{ str_replace('_', ' ', $permohonan->status) }}
                            </span>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Tanggal Pengajuan</p>
                            <p class="font-medium">{{ $permohonan->created_at->format('d M Y H:i:s') }}</p>
                        </div>
                        @if ($permohonan->petugas)
                            <div>
                                <p class="text-sm text-gray-500">Diproses Oleh</p>
                                <p class="font-medium">{{ $permohonan->petugas->nama }}</p>
                            </div>
                        @endif
                        @if ($permohonan->catatan_petugas)
                            <div class="col-span-2">
                                <p class="text-sm text-gray-500">Catatan Petugas</p>
                                <p class="font-medium bg-yellow-50 p-3 rounded border-l-4 border-yellow-400">{{ $permohonan->catatan_petugas }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Isi Form --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium border-b pb-2 mb-4">Isi Formulir</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($permohonan->data_form as $key => $value)
                            <div>
                                <p class="text-sm text-gray-500">{{ $labelFields[$key] ?? ucwords(str_replace('_', ' ', $key)) }}</p>
                                <p class="font-medium bg-gray-50 p-2 rounded">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Dokumen --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium border-b pb-2 mb-4">Dokumen Pendukung</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @forelse($dokumen as $doc)
                            <div class="border rounded p-4">
                                <p class="font-medium text-center mb-2 capitalize">{{ str_replace('_', ' ', $doc->jenis_dokumen) }}</p>
                                <div class="bg-gray-100 flex items-center justify-center rounded overflow-hidden border border-gray-200" style="height: 300px;">
                                    <img src="{{ route('dashboard.dokumen.preview', $doc->id) }}" alt="{{ $doc->jenis_dokumen }}" class="w-full h-full object-contain">
                                </div>
                                <div class="mt-3 text-center">
                                    <a href="{{ route('dashboard.dokumen.preview', $doc->id) }}" target="_blank" class="text-indigo-600 hover:text-indigo-900 text-sm">Lihat Penuh / Unduh</a>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500">Tidak ada dokumen pendukung yang dilampirkan.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Aksi: hanya tampil jika masih menunggu_verifikasi --}}
            @if ($permohonan->status === 'menunggu_verifikasi')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium border-b pb-2 mb-4">Tindakan</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            {{-- Panel Approve --}}
                            <div class="bg-green-50 border border-green-200 rounded-lg p-5">
                                <div class="mb-4">
                                    <h4 class="text-green-800 font-semibold mb-2">Setujui Permohonan</h4>
                                    <p class="text-sm text-green-700">Dokumen dan data form sudah sesuai. Lanjutkan ke proses pembuatan surat resmi.</p>
                                </div>
                                <div class="mt-4">
                                    <form method="POST" action="{{ route('dashboard.permohonan.approve', $permohonan->id) }}" onsubmit="return confirm('Setujui permohonan ini?')">
                                        @csrf
                                        <button type="submit" id="btn-approve" class="w-full px-4 py-3 bg-green-600 text-white font-semibold rounded hover:bg-green-700 transition shadow-sm">
                                            ✓ Setujui Permohonan
                                        </button>
                                    </form>
                                </div>
                            </div>

                            {{-- Panel Reject --}}
                            <div class="bg-red-50 border border-red-200 rounded-lg p-5">
                                <div class="mb-4">
                                    <h4 class="text-red-800 font-semibold mb-2">Tolak Permohonan</h4>
                                    <p class="text-sm text-red-700">Berkas tidak valid atau tidak lengkap. Berikan alasan penolakan kepada pemohon.</p>
                                </div>
                                <div class="mt-4">
                                    <form method="POST" action="{{ route('dashboard.permohonan.reject', $permohonan->id) }}">
                                        @csrf
                                        <div class="mb-3">
                                            <label for="catatan_petugas" class="block text-sm font-medium text-red-800 mb-1">
                                                Alasan Penolakan <span class="text-red-600">*</span>
                                            </label>
                                            <textarea id="catatan_petugas" name="catatan_petugas" rows="3"
                                                class="w-full border-red-300 rounded shadow-sm focus:ring-red-500 focus:border-red-500 @error('catatan_petugas') border-red-500 ring-1 ring-red-500 @enderror"
                                                placeholder="Wajib diisi. Minimal 10 karakter.">{{ old('catatan_petugas') }}</textarea>
                                            @error('catatan_petugas')
                                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                            @enderror
                                        </div>
                                        <button type="submit" id="btn-reject"
                                            class="w-full px-4 py-2 bg-red-600 text-white font-semibold rounded hover:bg-red-700 transition">
                                            ✗ Tolak Permohonan
                                        </button>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endif

            {{-- Tombol Terbitkan: hanya untuk status diproses --}}
            @if ($permohonan->status === 'diproses')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium border-b pb-2 mb-4">Terbitkan Surat</h3>
                        <p class="text-sm text-gray-600 mb-4">Permohonan ini telah diverifikasi. Klik tombol di bawah untuk membuat dan menerbitkan surat resmi dalam format PDF.</p>
                        <form method="POST" action="{{ route('dashboard.permohonan.terbitkan', $permohonan->id) }}"
                              onsubmit="return confirm('Terbitkan surat untuk permohonan ini? Tindakan ini tidak dapat dibatalkan.')">
                            @csrf
                            <button type="submit" id="btn-terbitkan"
                                class="px-8 py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition">
                                📄 Terbitkan Surat Resmi
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Info Surat Selesai --}}
            @if ($permohonan->status === 'selesai')
                <div class="bg-green-50 border border-green-200 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-green-800 border-b border-green-200 pb-2 mb-4">✅ Surat Telah Diterbitkan</h3>
                        <div class="space-y-2 text-sm">
                            <p><span class="font-semibold">Nomor Surat:</span> {{ $permohonan->nomor_surat }}</p>
                            @php
                                $signedUrl = URL::temporarySignedRoute(
                                    'surat.unduh',
                                    now()->addDays(30),
                                    ['permohonan' => $permohonan->id]
                                );
                            @endphp
                            <p>
                                <span class="font-semibold">Link Unduh Warga</span>
                                <span class="text-xs text-gray-500">(berlaku 30 hari):</span>
                            </p>
                            <div class="flex items-center gap-2">
                                <input type="text" readonly value="{{ $signedUrl }}"
                                    class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs bg-gray-50 font-mono">
                                <a href="{{ $signedUrl }}" target="_blank"
                                    class="px-3 py-1 bg-indigo-600 text-white text-xs rounded hover:bg-indigo-700 transition whitespace-nowrap">
                                    Pratinjau / Unduh PDF
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
