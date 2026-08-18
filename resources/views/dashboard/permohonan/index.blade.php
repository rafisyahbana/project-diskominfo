<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftar Permohonan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="mb-4 flex space-x-2">
                <a href="{{ route('dashboard.permohonan.index', ['status' => 'menunggu_verifikasi']) }}" class="px-4 py-2 bg-blue-500 text-white rounded-md {{ $status === 'menunggu_verifikasi' ? 'ring-2 ring-blue-300' : 'opacity-70 hover:opacity-100' }}">Menunggu Verifikasi</a>
                <a href="{{ route('dashboard.permohonan.index', ['status' => 'diproses']) }}" class="px-4 py-2 bg-yellow-500 text-white rounded-md {{ $status === 'diproses' ? 'ring-2 ring-yellow-300' : 'opacity-70 hover:opacity-100' }}">Diproses</a>
                <a href="{{ route('dashboard.permohonan.index', ['status' => 'selesai']) }}" class="px-4 py-2 bg-green-500 text-white rounded-md {{ $status === 'selesai' ? 'ring-2 ring-green-300' : 'opacity-70 hover:opacity-100' }}">Selesai</a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tgl / Waktu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NIK / No. WA</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jenis Surat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse ($permohonan as $p)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $p->created_at->format('d M Y H:i') }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $p->nik }}<br>
                                        <span class="text-gray-500 text-xs">{{ $p->no_wa }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">{{ str_replace('_', ' ', $p->jenis_surat) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $p->status === 'menunggu_verifikasi' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $p->status === 'diproses' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $p->status === 'selesai' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $p->status === 'ditolak' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ str_replace('_', ' ', $p->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('dashboard.permohonan.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-900">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">Tidak ada permohonan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $permohonan->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
