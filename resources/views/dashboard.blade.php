<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    <div class="dkm-card">
        <div class="dkm-card-body" style="padding: 2.5rem; text-align: center;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#2c6fbb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 1rem;">
                <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                <polyline points="22 4 12 14.01 9 11.01"/>
            </svg>
            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e3a5f; margin: 0 0 0.5rem;">Selamat datang, {{ Auth::guard('petugas')->user()->nama }}!</h2>
            <p style="color: #6b7280; font-size: 0.9375rem; margin: 0;">Anda telah berhasil masuk ke sistem pelayanan administrasi kependudukan.</p>
        </div>
    </div>
</x-app-layout>
