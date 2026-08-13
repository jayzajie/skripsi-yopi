<?php

namespace Database\Seeders;

use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = env('SIPADOK_DEMO_PASSWORD');
        if (! app()->environment('local') || ! $password) {
            return;
        }

        $super = Pengguna::updateOrCreate(['username' => 'superadmin'], [
            'name' => 'Admin Utama', 'email' => 'superadmin@slbpembina.sch.id',
            'password' => $password, 'peran' => 'super_admin', 'aktif' => true,
        ]);
        $admin = Pengguna::updateOrCreate(['username' => 'admin'], [
            'name' => 'Budi Santoso', 'email' => 'admin@slbpembina.sch.id',
            'password' => $password, 'peran' => 'admin', 'aktif' => true,
        ]);
        $pegawai = Pengguna::updateOrCreate(['username' => 'pegawai'], [
            'name' => 'Siti Rahma', 'email' => 'pegawai@slbpembina.sch.id',
            'password' => $password, 'peran' => 'pegawai', 'aktif' => true,
        ]);

        foreach ([
            ['Surat Masuk', 'SM'], ['Surat Keluar', 'SK'], ['Dokumen Akademik', 'DA'],
            ['Dokumen Kepegawaian', 'DK'], ['Dokumen Akreditasi', 'AK'], ['Hasil Asesmen', 'HA'],
            ['Rapor', 'RP'], ['Dokumen Umum', 'DU'],
        ] as [$nama, $kode]) {
            KategoriDokumen::firstOrCreate(['kode' => $kode], ['nama' => $nama, 'aktif' => true]);
        }
        $kategori = KategoriDokumen::pluck('id', 'kode');

        $contoh = [
            ['masuk', 'SM/2024/05/001', '121/SM/05/2024', '2024-05-21', 'Dinas Pendidikan', 'Undangan Rapat', 'Diterima'],
            ['masuk', 'SM/2024/05/002', '122/SM/05/2024', '2024-05-22', 'Dinas Sosial', 'Permohonan Data', 'Diterima'],
            ['masuk', 'SM/2024/05/003', '123/SM/05/2024', '2024-05-23', 'CV. Maju Bersama', 'Penawaran', 'Selesai'],
            ['masuk', 'SM/2024/05/004', '124/SM/05/2024', '2024-05-24', 'Orang Tua Siswa', 'Pemberitahuan', 'Selesai'],
            ['masuk', 'SM/2024/05/005', '125/SM/05/2024', '2024-05-25', 'Puskesmas', 'Kerjasama', 'Selesai'],
            ['keluar', 'SK/2024/05/001', '121/SK/05/2024', '2024-05-21', 'Dinas Pendidikan', 'Undangan Rapat', 'Terkirim'],
            ['keluar', 'SK/2024/05/002', '122/SK/05/2024', '2024-05-22', 'Dinas Sosial', 'Permohonan Data', 'Terkirim'],
            ['keluar', 'SK/2024/05/003', '123/SK/05/2024', '2024-05-23', 'CV. Maju Bersama', 'Penawaran', 'Terkirim'],
            ['keluar', 'SK/2024/05/004', '124/SK/05/2024', '2024-05-24', 'Orang Tua Siswa', 'Pemberitahuan', 'Terkirim'],
            ['keluar', 'SK/2024/05/005', '125/SK/05/2024', '2024-05-25', 'Puskesmas', 'Kerjasama', 'Terkirim'],
        ];
        foreach ($contoh as [$jenis, $agenda, $nomor, $tanggal, $pihak, $perihal, $status]) {
            Surat::firstOrCreate(['nomor_agenda' => $agenda], [
                'kategori_id' => $kategori[$jenis === 'masuk' ? 'SM' : 'SK'],
                'jenis' => $jenis, 'nomor_surat' => $nomor, 'tanggal_surat' => $tanggal,
                'pihak' => $pihak, 'perihal' => $perihal, 'status' => $status,
                'dibuat_oleh' => $admin->id,
                'ditugaskan_ke' => in_array($status, ['Selesai', 'Terkirim'], true) ? $pegawai->id : null,
                'disposisi' => in_array($status, ['Selesai', 'Terkirim'], true) ? 'Dokumen contoh untuk alur penugasan.' : null,
            ]);
        }
    }
}
