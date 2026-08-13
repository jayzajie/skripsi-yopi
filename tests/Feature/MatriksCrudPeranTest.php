<?php

namespace Tests\Feature;

use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MatriksCrudPeranTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_menjalankan_crud_surat_dan_super_admin_memantaunya(): void
    {
        Storage::fake('local');
        $kategori = KategoriDokumen::create(['nama' => 'Dokumen QA', 'kode' => 'QA', 'aktif' => true]);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        foreach (['masuk', 'keluar'] as $jenis) {
            $nomorAgenda = strtoupper("admin-{$jenis}-CRUD");
            $this->actingAs($admin)->post(route('surat.simpan', $jenis), [
                'kategori_id' => $kategori->id,
                'nomor_agenda' => $nomorAgenda,
                'nomor_surat' => '001/QA',
                'tanggal_surat' => '2026-08-08',
                'pihak' => 'Penguji QA',
                'perihal' => 'Dokumen awal',
                'file' => UploadedFile::fake()->createWithContent("{$jenis}-awal.pdf", '%PDF-1.7 awal'),
            ])->assertSessionHas('sukses');

            $surat = Surat::where('nomor_agenda', $nomorAgenda)->firstOrFail();
            $fileAwal = $surat->file;
            Storage::assertExists($fileAwal);
            $this->actingAs($admin)->get(route('surat', $jenis))->assertOk()->assertSee($nomorAgenda);
            $this->actingAs($superAdmin)->get(route('surat', $jenis))->assertOk()->assertSee($nomorAgenda);

            $this->actingAs($admin)->put(route('surat.perbarui', $surat), [
                'kategori_id' => $kategori->id,
                'nomor_agenda' => $nomorAgenda,
                'nomor_surat' => '002/QA',
                'tanggal_surat' => '2026-08-08',
                'pihak' => 'Penguji QA',
                'perihal' => 'Dokumen diperbarui',
                'file' => UploadedFile::fake()->createWithContent("{$jenis}-baru.pdf", '%PDF-1.7 baru'),
            ])->assertSessionHas('sukses');

            $surat->refresh();
            Storage::assertMissing($fileAwal);
            Storage::assertExists($surat->file);
            $this->assertSame('Dokumen diperbarui', $surat->perihal);

            $fileBaru = $surat->file;
            $this->actingAs($admin)->delete(route('surat.hapus', $surat))->assertSessionHas('sukses');
            $this->assertDatabaseMissing('surat', ['id' => $surat->id]);
            Storage::assertMissing($fileBaru);
        }
    }

    public function test_super_admin_menjalankan_crud_master_data_dan_akun(): void
    {
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('kategori.simpan'), [
            'nama' => 'Dokumen QA', 'kode' => 'QA', 'deskripsi' => 'Awal', 'aktif' => true,
        ])->assertSessionHas('sukses');
        $kategori = KategoriDokumen::where('kode', 'QA')->firstOrFail();
        $this->actingAs($superAdmin)->put(route('kategori.perbarui', $kategori), [
            'nama' => 'Dokumen QA Baru', 'kode' => 'QA', 'deskripsi' => 'Diperbarui', 'aktif' => false,
        ])->assertSessionHas('sukses');
        $this->assertDatabaseHas('kategori_dokumen', ['id' => $kategori->id, 'nama' => 'Dokumen QA Baru', 'aktif' => false]);

        $this->actingAs($superAdmin)->post(route('akun.simpan'), [
            'name' => 'Pegawai QA', 'username' => 'pegawai_qa', 'email' => 'pegawai.qa@example.test',
            'password' => 'rahasia-qa', 'peran' => 'pegawai', 'aktif' => true,
        ])->assertSessionHas('sukses');
        $pegawai = Pengguna::where('username', 'pegawai_qa')->firstOrFail();
        $hashAwal = $pegawai->password;
        $this->assertTrue(Hash::check('rahasia-qa', $hashAwal));

        $this->actingAs($superAdmin)->put(route('akun.perbarui', $pegawai), [
            'name' => 'Pegawai QA Baru', 'username' => 'pegawai_qa', 'email' => 'pegawai.baru@example.test',
            'password' => null, 'peran' => 'pegawai', 'aktif' => false,
        ])->assertSessionHas('sukses');
        $pegawai->refresh();
        $this->assertSame($hashAwal, $pegawai->password);
        $this->assertFalse($pegawai->aktif);

        $this->actingAs($superAdmin)->delete(route('akun.hapus', $superAdmin))->assertStatus(422);
        $this->actingAs($superAdmin)->delete(route('akun.hapus', $pegawai))->assertSessionHas('sukses');
        $this->assertDatabaseMissing('users', ['id' => $pegawai->id]);
    }

    public function test_matriks_akses_tiga_role_sesuai_userflow(): void
    {
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);

        foreach (['/dasbor', '/arsip', '/laporan'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($pegawai)->get($url)->assertOk();
        }
        foreach (['/surat/masuk', '/surat/keluar'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($pegawai)->get($url)->assertForbidden();
        }
        foreach (['/master-data', '/akun', '/cadangan', '/verifikasi-ttd'] as $url) {
            $this->actingAs($superAdmin)->get($url)->assertOk();
            $this->actingAs($admin)->get($url)->assertForbidden();
            $this->actingAs($pegawai)->get($url)->assertForbidden();
        }

        $this->actingAs($pegawai)->post(route('surat.simpan', 'masuk'), [])->assertForbidden();
        $this->actingAs($superAdmin)->post(route('surat.simpan', 'masuk'), [])->assertForbidden();
        $this->actingAs($admin)->post(route('kategori.simpan'), [])->assertForbidden();
    }
}
