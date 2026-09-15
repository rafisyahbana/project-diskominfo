<?php

namespace App\Services;

class ReferensiService
{
    /**
     * Label manusiawi untuk setiap field form.
     * Dipakai oleh ConversationOrchestrator agar warga tidak melihat snake_case mentah.
     */
    public const LABEL_FIELD = [
        'nama_lengkap'         => 'nama lengkap',
        'alamat_tinggal'       => 'alamat tempat tinggal',
        'lama_tinggal'         => 'lama tinggal (contoh: 3 tahun)',
        'keperluan'            => 'keperluan surat',
        'pekerjaan'            => 'pekerjaan',
        'jumlah_tanggungan'    => 'jumlah tanggungan keluarga',
        'alasan_pengajuan'     => 'alasan pengajuan SKTM',
        'nama_usaha'           => 'nama usaha',
        'mulai_usaha_sejak'    => 'mulai usaha sejak (tahun)',
        'alamat_usaha'         => 'alamat lengkap tempat usaha',
        'tujuan_penggunaan'    => 'tujuan penggunaan',
        'status_perkawinan'    => 'status perkawinan (Belum Kawin/Kawin/Cerai)',
        'nama_anak'            => 'nama lengkap anak',
        'jenis_kelamin_anak'   => 'jenis kelamin anak (Laki-laki/Perempuan)',
        'anak_ke'              => 'anak ke-berapa',
        'waktu_lahir'          => 'waktu lahir (hari, tanggal, jam)',
        'berat_badan'          => 'berat badan lahir (kg/gram)',
        'nama_ayah'            => 'nama ayah kandung',
        'nama_ibu'             => 'nama ibu kandung',
        'nama_almarhum'        => 'nama lengkap almarhum/almarhumah',
        'nik_almarhum'         => 'NIK almarhum/almarhumah',
        'tanggal_meninggal'    => 'waktu meninggal (hari, tanggal, jam)',
        'sebab_kematian'       => 'sebab kematian',
        'lokasi_dimakamkan'    => 'lokasi pemakaman',
        'alamat_tujuan'        => 'alamat lengkap tujuan pindah',
        'alasan_pindah'        => 'alasan pindah',
        'jumlah_anggota_pindah'=> 'jumlah anggota keluarga yang pindah',
        'rata_penghasilan_perbulan' => 'rata-rata penghasilan per bulan',
        'lokasi_tanah'         => 'alamat/lokasi bidang tanah',
        'luas_tanah'           => 'luas tanah (meter persegi)',
        'batas_utara'          => 'batas utara tanah',
        'batas_selatan'        => 'batas selatan tanah',
        'batas_timur'          => 'batas timur tanah',
        'batas_barat'          => 'batas barat tanah',
        'nama_pewaris'         => 'nama pewaris (almarhum)',
        'tanggal_meninggal_pewaris' => 'tanggal meninggal pewaris',
        'nama_ahli_waris'      => 'daftar nama ahli waris (pisahkan dengan koma)',
        'hubungan_ahli_waris'  => 'hubungan ahli waris (misal: istri, anak)',
        'nama_di_ktp'          => 'nama sesuai KTP',
        'nama_di_dokumen_lain' => 'nama pada dokumen lain (yang salah)',
        'nama_dokumen_berbeda' => 'nama dokumen yang berbeda (misal: Ijazah/Sertifikat)',
        'nama_calon_pasangan'  => 'nama lengkap calon suami/istri',
        'lokasi_akad'          => 'rencana lokasi akad/KUA',
        'tanggal_akad'         => 'rencana tanggal akad',
        'judul_keterangan'     => 'judul surat keterangan',
        'keterangan_detail'    => 'isi/detail keterangan yang diminta',
    ];

    /**
     * Mengembalikan daftar syarat, estimasi waktu, pertanyaan form,
     * dan dokumen wajib per jenis surat.
     * Return null jika jenis tidak dikenal.
     */
    public function syarat(string $jenis_surat): ?array
    {
        $katalog = [
            'domisili' => [
                'jenis_surat'     => 'domisili',
                'nama'            => 'Surat Keterangan Domisili',
                'estimasi_waktu'  => '1-2 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_lengkap', 'alamat_tinggal', 'lama_tinggal', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'sktm' => [
                'jenis_surat'     => 'sktm',
                'nama'            => 'Surat Keterangan Tidak Mampu',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_lengkap', 'pekerjaan', 'jumlah_tanggungan', 'alasan_pengajuan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'usaha' => [
                'jenis_surat'     => 'usaha',
                'nama'            => 'Surat Keterangan Usaha',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['pekerjaan', 'nama_usaha', 'mulai_usaha_sejak', 'alamat_usaha', 'tujuan_penggunaan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'skck' => [
                'jenis_surat'     => 'skck',
                'nama'            => 'Surat Pengantar SKCK',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['status_perkawinan', 'pekerjaan', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'belum_menikah' => [
                'jenis_surat'     => 'belum_menikah',
                'nama'            => 'Surat Keterangan Belum Pernah Menikah',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['pekerjaan', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'kelahiran' => [
                'jenis_surat'     => 'kelahiran',
                'nama'            => 'Surat Keterangan Kelahiran',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_anak', 'jenis_kelamin_anak', 'anak_ke', 'waktu_lahir', 'berat_badan', 'nama_ayah', 'nama_ibu'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'kematian' => [
                'jenis_surat'     => 'kematian',
                'nama'            => 'Surat Keterangan Kematian',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_almarhum', 'nik_almarhum', 'tanggal_meninggal', 'sebab_kematian', 'lokasi_dimakamkan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'pindah' => [
                'jenis_surat'     => 'pindah',
                'nama'            => 'Surat Pengantar Pindah',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['alamat_tujuan', 'alasan_pindah', 'jumlah_anggota_pindah'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'penghasilan' => [
                'jenis_surat'     => 'penghasilan',
                'nama'            => 'Surat Keterangan Penghasilan',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['pekerjaan', 'jumlah_tanggungan', 'rata_penghasilan_perbulan', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'tanah' => [
                'jenis_surat'     => 'tanah',
                'nama'            => 'Surat Keterangan Tanah',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['lokasi_tanah', 'luas_tanah', 'batas_utara', 'batas_selatan', 'batas_timur', 'batas_barat'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'ahli_waris' => [
                'jenis_surat'     => 'ahli_waris',
                'nama'            => 'Surat Keterangan Ahli Waris',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_pewaris', 'tanggal_meninggal_pewaris', 'nama_ahli_waris', 'hubungan_ahli_waris'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'beda_nama' => [
                'jenis_surat'     => 'beda_nama',
                'nama'            => 'Surat Keterangan Beda Nama',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['nama_di_ktp', 'nama_di_dokumen_lain', 'nama_dokumen_berbeda', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'nikah' => [
                'jenis_surat'     => 'nikah',
                'nama'            => 'Surat Pengantar Nikah Model N1-N4',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['status_perkawinan', 'nama_calon_pasangan', 'lokasi_akad', 'tanggal_akad'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
            'lainnya' => [
                'jenis_surat'     => 'lainnya',
                'nama'            => 'Surat Keterangan Lainnya',
                'estimasi_waktu'  => '2-5 hari kerja',
                'syarat' => ['Fotokopi KTP', 'Fotokopi Kartu Keluarga (KK)'],
                'pertanyaan_form' => ['judul_keterangan', 'keterangan_detail', 'keperluan'],
                'dokumen_wajib' => ['fotokopi_ktp', 'fotokopi_kk'],
            ],
        ];

        return $katalog[$jenis_surat] ?? null;
    }

    /**
     * Helper — daftar pertanyaan form saja (urutan penting).
     */
    public function getPertanyaanForm(string $jenisSurat): array
    {
        return $this->syarat($jenisSurat)['pertanyaan_form'] ?? [];
    }

    /**
     * Helper — daftar dokumen wajib saja (urutan menentukan urutan upload).
     */
    public function getDokumenWajib(string $jenisSurat): array
    {
        return $this->syarat($jenisSurat)['dokumen_wajib'] ?? [];
    }
}
