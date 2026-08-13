<?php

namespace Tests\Feature;

use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurTigaPeranTest extends TestCase
{
    use RefreshDatabase;

    public function test_surat_masuk_mengalir_dari_admin_ke_super_admin_lalu_pegawai(): void
    {
        Storage::fake('local');
        $kategori = KategoriDokumen::create(['nama' => 'Surat Masuk', 'kode' => 'SM', 'aktif' => true]);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);
        $pegawaiLain = Pengguna::factory()->create(['peran' => 'pegawai']);

        $this->actingAs($admin)->post(route('surat.simpan', 'masuk'), [
            'kategori_id' => $kategori->id,
            'nomor_agenda' => 'SM/ALUR/001',
            'nomor_surat' => '001/ALUR',
            'tanggal_surat' => '2026-08-12',
            'pihak' => 'Dinas Pendidikan',
            'perihal' => 'Permintaan tindak lanjut',
            'file' => UploadedFile::fake()->createWithContent('masuk.pdf', '%PDF-1.7'),
        ])->assertSessionHas('sukses');

        $surat = Surat::where('nomor_agenda', 'SM/ALUR/001')->firstOrFail();
        $this->assertSame('Diterima', $surat->status);
        $this->assertSame($kategori->id, $surat->kategori_id);

        $this->actingAs($superAdmin)->post(route('surat.proses', $surat), [
            'ditugaskan_ke' => $pegawai->id,
            'disposisi' => 'Siapkan bahan jawaban.',
        ])->assertSessionHas('sukses');

        $surat->refresh();
        $this->assertSame('Didisposisikan', $surat->status);
        $this->assertSame($pegawai->id, $surat->ditugaskan_ke);
        $this->actingAs($pegawai)->get(route('dasbor'))->assertOk()->assertSee('SM/ALUR/001');
        $this->actingAs($pegawaiLain)->get(route('dasbor'))->assertOk()->assertDontSee('SM/ALUR/001');

        $this->actingAs($pegawai)->post(route('surat.selesaikan', $surat))->assertSessionHas('sukses');
        $this->assertDatabaseHas('surat', ['id' => $surat->id, 'status' => 'Selesai']);
    }

    public function test_surat_keluar_baru_terlihat_pegawai_setelah_disetujui_dan_dikirim(): void
    {
        $kategori = KategoriDokumen::create(['nama' => 'Surat Keluar', 'kode' => 'SK', 'aktif' => true]);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);

        $this->actingAs($admin)->post(route('surat.simpan', 'keluar'), [
            'kategori_id' => $kategori->id,
            'nomor_agenda' => 'SK/ALUR/001',
            'nomor_surat' => '002/ALUR',
            'tanggal_surat' => '2026-08-12',
            'pihak' => 'Dinas Sosial',
            'perihal' => 'Jawaban resmi',
        ])->assertSessionHas('sukses');

        $surat = Surat::where('nomor_agenda', 'SK/ALUR/001')->firstOrFail();
        $this->assertSame('Konsep', $surat->status);
        $this->actingAs($superAdmin)->post(route('surat.proses', $surat), [
            'ditugaskan_ke' => $pegawai->id,
            'disposisi' => 'Disetujui untuk dikirim.',
        ])->assertSessionHas('sukses');
        $this->actingAs($pegawai)->get(route('arsip'))->assertDontSee('SK/ALUR/001');

        $this->actingAs($admin)->post(route('surat.terkirim', $surat))->assertSessionHas('sukses');
        $this->actingAs($pegawai)->get(route('arsip'))->assertSee('SK/ALUR/001');
    }

    public function test_verifikasi_tanda_tangan_terhubung_ke_surat_tersimpan(): void
    {
        Storage::fake('local');
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $surat = $this->buatSurat(['file' => 'surat/dokumen.pdf']);
        Storage::put($surat->file, '%PDF-1.7 tanpa tanda tangan');

        $this->actingAs($superAdmin)->post(route('verifikasi.proses', $surat))->assertSessionHas('sukses');

        $this->assertDatabaseHas('verifikasi_ttd', [
            'surat_id' => $surat->id,
            'nama_file' => 'dokumen.pdf',
            'valid' => false,
        ]);
    }

    public function test_hanya_super_admin_yang_mengelola_konfigurasi_dan_admin_yang_mengelola_surat(): void
    {
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        foreach (['/master-data', '/akun', '/cadangan', '/verifikasi-ttd'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
            $this->actingAs($admin)->get($url)->assertForbidden();
        }

        $this->actingAs($admin)->post(route('surat.simpan', 'masuk'), [])->assertSessionHasErrors();
        $this->actingAs($superAdmin)->post(route('surat.simpan', 'masuk'), [])->assertForbidden();
    }

    public function test_sesi_pengguna_berakhir_setelah_akunnya_dinonaktifkan(): void
    {
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai', 'aktif' => true]);
        $this->actingAs($pegawai);
        $pegawai->update(['aktif' => false]);

        $this->get(route('dasbor'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_transisi_tidak_sah_ditolak_tanpa_mengubah_status(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);
        $pegawaiLain = Pengguna::factory()->create(['peran' => 'pegawai']);

        $masuk = $this->buatSurat(['status' => 'Didisposisikan', 'ditugaskan_ke' => $pegawai->id]);
        $keluar = $this->buatSurat(['jenis' => 'keluar', 'nomor_agenda' => 'SK/TEST/001', 'status' => 'Konsep']);

        $this->actingAs($pegawaiLain)->post(route('surat.selesaikan', $masuk))->assertForbidden();
        $this->actingAs($superAdmin)->post(route('surat.proses', $masuk), [
            'ditugaskan_ke' => $pegawaiLain->id,
            'disposisi' => 'Ulangi proses.',
        ])->assertUnprocessable();
        $this->actingAs($admin)->post(route('surat.terkirim', $keluar))->assertUnprocessable();
        $this->actingAs($admin)->delete(route('surat.hapus', $masuk))->assertUnprocessable();

        $this->assertDatabaseHas('surat', ['id' => $masuk->id, 'status' => 'Didisposisikan', 'ditugaskan_ke' => $pegawai->id]);
        $this->assertDatabaseHas('surat', ['id' => $keluar->id, 'status' => 'Konsep']);
    }

    private function buatSurat(array $tambahan = []): Surat
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);

        return Surat::create(array_merge([
            'jenis' => 'masuk',
            'nomor_agenda' => 'SM/TEST/001',
            'nomor_surat' => '001/TEST',
            'tanggal_surat' => '2026-08-12',
            'pihak' => 'Dinas Pendidikan',
            'perihal' => 'Dokumen pengujian',
            'status' => 'Diterima',
            'dibuat_oleh' => $admin->id,
        ], $tambahan));
    }
}
