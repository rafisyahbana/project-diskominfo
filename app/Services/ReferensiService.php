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
        'keperluan_surat'      => 'keperluan surat',
        'tujuan_instansi'      => 'tujuan/nama instansi',
        'jenis_surat_spesifik' => 'jenis surat yang diinginkan',
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
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Materai Rp10.000',
                ],
                'pertanyaan_form' => [
                    'nama_lengkap',
                    'alamat_tinggal',
                    'lama_tinggal',
                    'keperluan',
                ],
                'dokumen_wajib' => [
                    'fotokopi_ktp',
                    'fotokopi_kk',
                ],
            ],
            'sktm' => [
                'jenis_surat'     => 'sktm',
                'nama'            => 'Surat Keterangan Tidak Mampu',
                'estimasi_waktu'  => '1-3 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Surat permohonan bermaterai',
                    'Dokumen pendukung (tagihan, kondisi rumah, dll)',
                ],
                'pertanyaan_form' => [
                    'nama_lengkap',
                    'pekerjaan',
                    'jumlah_tanggungan',
                    'alasan_pengajuan',
                ],
                'dokumen_wajib' => [
                    'fotokopi_ktp',
                    'fotokopi_kk',
                    'dokumen_pendukung',
                ],
            ],
            'pengantar' => [
                'jenis_surat'     => 'pengantar',
                'nama'            => 'Surat Pengantar',
                'estimasi_waktu'  => '1 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Surat pengantar RT/RW',
                    'Keterangan keperluan surat',
                ],
                'pertanyaan_form' => [
                    'nama_lengkap',
                    'keperluan_surat',
                    'tujuan_instansi',
                ],
                'dokumen_wajib' => [
                    'fotokopi_ktp',
                ],
            ],
            'lainnya' => [
                'jenis_surat'     => 'lainnya',
                'nama'            => 'Surat Keterangan Lainnya',
                'estimasi_waktu'  => '2-5 hari kerja',
                'syarat' => [
                    'Fotokopi KTP',
                    'Fotokopi Kartu Keluarga (KK)',
                    'Surat pengantar RT/RW',
                    'Dokumen pendukung sesuai keperluan',
                    'Surat permohonan bermaterai',
                ],
                'pertanyaan_form' => [
                    'nama_lengkap',
                    'jenis_surat_spesifik',
                    'keperluan',
                ],
                'dokumen_wajib' => [
                    'fotokopi_ktp',
                    'fotokopi_kk',
                    'dokumen_pendukung',
                ],
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
