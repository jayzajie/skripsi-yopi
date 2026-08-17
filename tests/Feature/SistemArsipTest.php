<?php

namespace Tests\Feature;

use App\Layanan\PemulihCadangan;
use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SistemArsipTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_aktif_dapat_masuk_dengan_username(): void
    {
        $this->withoutVite();
        $pengguna = Pengguna::factory()->create(['username' => 'admin', 'peran' => 'admin']);

        $respons = $this->post('/masuk', ['username' => 'admin', 'password' => 'password']);

        $respons->assertRedirect('/dasbor');
        $this->assertAuthenticatedAs($pengguna);
    }

    public function test_pegawai_ditolak_dari_pengelolaan_surat(): void
    {
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);

        $respons = $this->actingAs($pegawai)->get('/surat/masuk');

        $respons->assertForbidden();
    }

    public function test_admin_dapat_menyimpan_surat_masuk(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $kategori = KategoriDokumen::create(['nama' => 'Surat Masuk', 'kode' => 'SM', 'aktif' => true]);

        $respons = $this->actingAs($admin)->post('/surat/masuk', [
            'kategori_id' => $kategori->id,
            'nomor_agenda' => 'SM/TEST/001',
            'nomor_surat' => '001/TEST',
            'tanggal_surat' => '2026-08-07',
            'pihak' => 'Dinas Pendidikan',
            'perihal' => 'Undangan Pengujian',
        ]);

        $respons->assertSessionHas('sukses');
        $this->assertDatabaseHas(Surat::class, ['nomor_agenda' => 'SM/TEST/001']);
    }

    public function test_super_admin_mengelola_akun_dan_admin_ditolak(): void
    {
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);

        $this->actingAs($superAdmin)->get('/akun')->assertOk();
        $this->actingAs(Pengguna::factory()->create(['peran' => 'admin']))->get('/akun')->assertForbidden();
    }

    public function test_akun_nonaktif_tidak_dapat_masuk(): void
    {
        Pengguna::factory()->create(['username' => 'nonaktif', 'aktif' => false]);

        $respons = $this->post('/masuk', ['username' => 'nonaktif', 'password' => 'password']);

        $respons->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_struktur_tanda_tangan_pdf_tercatat_valid(): void
    {
        Storage::fake('local');
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        [$pdf, $sertifikat] = $this->buatPdfTertanda();
        $trustStore = tempnam(sys_get_temp_dir(), 'sipadok-ca-');
        file_put_contents($trustStore, $sertifikat);
        config(['app.pdf_trust_store' => $trustStore]);
        Storage::put('surat/tertanda.pdf', $pdf);
        $surat = $this->buatSurat($admin, ['file' => 'surat/tertanda.pdf']);

        $respons = $this->actingAs($superAdmin)->post(route('verifikasi.proses', $surat));
        @unlink($trustStore);

        $respons->assertSessionHas('sukses');
        $this->assertDatabaseHas('verifikasi_ttd', ['nama_file' => 'tertanda.pdf', 'valid' => true]);
    }

    public function test_sertifikat_yang_tidak_dipercaya_ditolak(): void
    {
        Storage::fake('local');
        [$pdf] = $this->buatPdfTertanda();
        Storage::put('surat/tidak-dipercaya.pdf', $pdf);
        $surat = $this->buatSurat(Pengguna::factory()->create(['peran' => 'admin']), [
            'file' => 'surat/tidak-dipercaya.pdf',
        ]);

        $this->actingAs(Pengguna::factory()->create(['peran' => 'super_admin']))
            ->post(route('verifikasi.proses', $surat))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('verifikasi_ttd', ['surat_id' => $surat->id, 'valid' => false]);
    }

    public function test_tanda_tangan_visual_dan_scan_tercatat_valid(): void
    {
        Storage::fake('local');
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $dokumen = [
            ['gambar.pdf', 'VISUAL-1', '%PDF-1.7 /Subtype /Image /Width 542 /Height 169 stream ttd endstream', true],
            ['scan.pdf', 'VISUAL-2', '%PDF-1.7 /Subtype /Image /Width 1117 /Height 1600 stream scan endstream', true],
            ['tanpa-ttd.pdf', 'VISUAL-3', '%PDF-1.7 dokumen teks tanpa gambar', false],
        ];

        foreach ($dokumen as [$nama, $agenda, $isi, $valid]) {
            Storage::put("surat/{$nama}", $isi);
            $surat = $this->buatSurat($admin, ['nomor_agenda' => $agenda, 'file' => "surat/{$nama}"]);
            $this->actingAs($superAdmin)->post(route('verifikasi.proses', $surat))->assertSessionHas('sukses');
            $this->assertDatabaseHas('verifikasi_ttd', ['surat_id' => $surat->id, 'valid' => $valid]);
        }
    }

    public function test_pratinjau_memakai_pdf_asli_dan_pegawai_tidak_dapat_membuka_draf(): void
    {
        Storage::fake('local');
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);
        Storage::put('surat/asli.pdf', '%PDF-1.7 isi dokumen');
        $surat = $this->buatSurat($admin, ['status' => 'Konsep', 'file' => 'surat/asli.pdf']);

        $this->actingAs($admin)->get(route('arsip.pratinjau', $surat))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($pegawai)->get(route('arsip.detail', $surat))->assertForbidden();
    }

    public function test_ekspor_laporan_mengikuti_filter_jenis(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $this->buatSurat($admin, ['jenis' => 'masuk', 'nomor_agenda' => 'MASUK-TERPILIH']);
        $this->buatSurat($admin, ['jenis' => 'keluar', 'nomor_agenda' => 'KELUAR-TERSEMBUNYI']);

        $respons = $this->actingAs($admin)->get(route('laporan.ekspor', ['jenis' => 'masuk']));

        $respons->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringContainsString('MASUK-TERPILIH', $respons->streamedContent());
        $this->assertStringNotContainsString('KELUAR-TERSEMBUNYI', $respons->streamedContent());
    }

    public function test_laporan_pegawai_hanya_memuat_arsip_final(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);
        $this->buatSurat($admin, ['nomor_agenda' => 'FINAL-TERLIHAT', 'status' => 'Selesai', 'ditugaskan_ke' => $pegawai->id]);
        $this->buatSurat($admin, ['nomor_agenda' => 'DRAF-TERSEMBUNYI', 'status' => 'Konsep', 'ditugaskan_ke' => $pegawai->id]);

        $this->actingAs($pegawai)->get('/laporan')
            ->assertOk()->assertSee('FINAL-TERLIHAT')->assertDontSee('DRAF-TERSEMBUNYI');
        $ekspor = $this->actingAs($pegawai)->get(route('laporan.ekspor'));
        $this->assertStringContainsString('FINAL-TERLIHAT', $ekspor->streamedContent());
        $this->assertStringNotContainsString('DRAF-TERSEMBUNYI', $ekspor->streamedContent());
    }

    public function test_cadangan_zip_memuat_database_dan_file_arsip(): void
    {
        Storage::fake('local');
        $admin = Pengguna::factory()->create(['peran' => 'super_admin']);
        Storage::put('surat/contoh.pdf', '%PDF-1.7');

        $this->actingAs($admin)->post(route('cadangan.buat'))->assertSessionHas('sukses');

        $nama = $this->assertDatabaseCount('cadangan_data', 1)->getConnection()
            ->table('cadangan_data')->value('nama_file');
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open(Storage::path("cadangan/{$nama}")) === true);
        $this->assertNotFalse($zip->locateName('database/data.json'));
        $snapshot = json_decode($zip->getFromName('database/data.json'), true);
        $this->assertSame('sipadok-mysql-v1', $snapshot['format']);
        $this->assertNotEmpty($snapshot['tabel']['users']);
        $this->assertNotFalse($zip->locateName('arsip/surat/contoh.pdf'));
        $zip->close();
    }

    public function test_pemulihan_mengganti_database_dan_snapshot_file_secara_utuh(): void
    {
        Storage::fake('local');
        $lokasiZip = tempnam(sys_get_temp_dir(), 'sipadok-zip-');
        $admin = Pengguna::factory()->create(['username' => 'admin-cadangan']);
        $namaTabel = ['users', 'kategori_dokumen', 'surat', 'verifikasi_ttd', 'cadangan_data'];
        $tabel = collect($namaTabel)->mapWithKeys(fn ($nama) => [
            $nama => DB::table($nama)->get()->map(fn ($baris) => (array) $baris)->all(),
        ])->all();
        Pengguna::factory()->create(['username' => 'pengguna-usang']);
        Storage::put('surat/usang.pdf', 'usang');
        $zip = new \ZipArchive;
        $zip->open($lokasiZip, \ZipArchive::OVERWRITE);
        $zip->addFromString('database/data.json', json_encode([
            'format' => 'sipadok-mysql-v1', 'tabel' => $tabel,
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('arsip/surat/baru.pdf', 'baru');
        $zip->close();

        $unggahan = UploadedFile::fake()->createWithContent('cadangan.zip', file_get_contents($lokasiZip));
        (new PemulihCadangan)->pulihkan($unggahan);

        Storage::assertMissing('surat/usang.pdf');
        Storage::assertExists('surat/baru.pdf');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'username' => 'admin-cadangan']);
        $this->assertDatabaseMissing('users', ['username' => 'pengguna-usang']);
        @unlink($lokasiZip);
    }

    public function test_alur_baca_super_admin_dan_pegawai_berjalan_sesuai_peran(): void
    {
        Storage::fake('local');
        $superAdmin = Pengguna::factory()->create(['peran' => 'super_admin']);
        $pegawai = Pengguna::factory()->create(['peran' => 'pegawai']);
        Storage::put('surat/akses.pdf', '%PDF-1.7');
        $surat = $this->buatSurat($superAdmin, ['file' => 'surat/akses.pdf', 'status' => 'Selesai', 'ditugaskan_ke' => $pegawai->id]);

        $this->actingAs($superAdmin)->get('/surat/masuk')->assertOk();
        $this->actingAs($superAdmin)->get('/verifikasi-ttd')->assertOk();
        $this->actingAs($pegawai)->get('/arsip')->assertOk()->assertSee($surat->nomor_agenda);
        $this->actingAs($pegawai)->get(route('arsip.detail', $surat))->assertOk();
        $this->actingAs($pegawai)->get(route('arsip.unduh', $surat))->assertOk();
        $this->actingAs($pegawai)->get('/laporan')->assertOk();
    }

    public function test_filter_jenis_arsip_dan_batas_percobaan_login_berfungsi(): void
    {
        $admin = Pengguna::factory()->create(['peran' => 'admin']);
        $this->buatSurat($admin, ['jenis' => 'masuk', 'nomor_agenda' => 'FILTER-MASUK']);
        $this->buatSurat($admin, ['jenis' => 'keluar', 'nomor_agenda' => 'FILTER-KELUAR', 'status' => 'Terkirim']);

        $this->actingAs($admin)->get('/arsip?jenis=keluar')
            ->assertOk()->assertSee('FILTER-KELUAR')->assertDontSee('FILTER-MASUK');
        auth()->logout();
        for ($percobaan = 0; $percobaan < 5; $percobaan++) {
            $this->post('/masuk', ['username' => 'tidak-ada', 'password' => 'keliru']);
        }
        $this->post('/masuk', ['username' => 'tidak-ada', 'password' => 'keliru'])->assertTooManyRequests();
    }

    private function buatSurat(Pengguna $pembuat, array $tambahan = []): Surat
    {
        return Surat::create(array_merge([
            'jenis' => 'masuk',
            'nomor_agenda' => 'SM/'.fake()->unique()->numerify('#####'),
            'nomor_surat' => fake()->numerify('###/TEST'),
            'tanggal_surat' => '2026-08-07',
            'pihak' => 'Dinas Pendidikan',
            'perihal' => 'Dokumen Pengujian',
            'status' => 'Diterima',
            'dibuat_oleh' => $pembuat->id,
        ], $tambahan));
    }

    private function buatPdfTertanda(): array
    {
        $penampung = str_repeat('0', 8192);
        $pola = '[0 ########## ########## ##########]';
        $pdf = "%PDF-1.7\n1 0 obj\n<< /Type /Sig /ByteRange {$pola} /Contents <{$penampung}> >>\nendobj\n%%EOF";
        $awalCelah = strpos($pdf, '<', strpos($pdf, '/Contents'));
        $akhirCelah = strpos($pdf, '>', $awalCelah) + 1;
        $rentang = sprintf('[0 %010d %010d %010d]', $awalCelah, $akhirCelah, strlen($pdf) - $akhirCelah);
        $pdf = str_replace($pola, $rentang, $pdf);
        $data = substr($pdf, 0, $awalCelah).substr($pdf, $akhirCelah);

        $kunci = openssl_pkey_new(['private_key_bits' => 2048]);
        $permintaan = openssl_csr_new(['commonName' => 'Pengujian SIPADOK'], $kunci);
        $sertifikat = openssl_csr_sign($permintaan, null, $kunci, 1);
        openssl_x509_export($sertifikat, $sertifikatPem);
        $lokasiData = tempnam(sys_get_temp_dir(), 'sipadok-data-');
        $lokasiCms = tempnam(sys_get_temp_dir(), 'sipadok-cms-');
        file_put_contents($lokasiData, $data);
        $this->assertTrue(openssl_cms_sign(
            $lokasiData,
            $lokasiCms,
            $sertifikat,
            $kunci,
            [],
            OPENSSL_CMS_BINARY | OPENSSL_CMS_DETACHED,
            OPENSSL_ENCODING_DER,
        ));
        $heksadesimal = bin2hex(file_get_contents($lokasiCms));
        @unlink($lokasiData);
        @unlink($lokasiCms);
        $this->assertLessThanOrEqual(strlen($penampung), strlen($heksadesimal));

        return [
            substr_replace($pdf, str_pad($heksadesimal, strlen($penampung), '0'), $awalCelah + 1, strlen($penampung)),
            $sertifikatPem,
        ];
    }
}
