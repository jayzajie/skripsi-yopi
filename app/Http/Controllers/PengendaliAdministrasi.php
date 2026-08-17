<?php

namespace App\Http\Controllers;

use App\Layanan\PemeriksaTandaTanganPdf;
use App\Layanan\PemulihCadangan;
use App\Models\CadanganData;
use App\Models\KategoriDokumen;
use App\Models\Pengguna;
use App\Models\Surat;
use App\Models\VerifikasiTtd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class PengendaliAdministrasi extends Controller
{
    public function masterData(): View
    {
        return view('aplikasi', ['kategori' => KategoriDokumen::orderBy('nama')->get(), 'halaman' => 'master-data']);
    }

    public function simpanKategori(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kode' => ['required', 'string', 'max:20', 'unique:kategori_dokumen,kode'],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'aktif' => ['required', 'boolean'],
        ]);
        KategoriDokumen::create($data);

        return back()->with('sukses', 'Kategori berhasil ditambahkan.');
    }

    public function perbaruiKategori(Request $request, KategoriDokumen $kategori): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'kode' => ['required', 'string', 'max:20', Rule::unique('kategori_dokumen', 'kode')->ignore($kategori)],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'aktif' => ['required', 'boolean'],
        ]);
        $kategori->update($data);

        return back()->with('sukses', 'Kategori berhasil diperbarui.');
    }

    public function hapusKategori(KategoriDokumen $kategori): RedirectResponse
    {
        abort_if($kategori->surat()->exists(), 422, 'Kategori yang sudah digunakan tidak dapat dihapus.');
        $kategori->delete();

        return back()->with('sukses', 'Kategori berhasil dihapus.');
    }

    public function akun(): View
    {
        return view('aplikasi', ['pengguna' => Pengguna::orderBy('name')->get(), 'halaman' => 'akun']);
    }

    public function simpanAkun(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'peran' => ['required', Rule::in(['super_admin', 'admin', 'pegawai'])],
            'aktif' => ['required', 'boolean'],
        ]);
        Pengguna::create($data);

        return back()->with('sukses', 'Akun berhasil ditambahkan.');
    }

    public function perbaruiAkun(Request $request, Pengguna $pengguna): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', Rule::unique('users')->ignore($pengguna)],
            'email' => ['required', 'email', Rule::unique('users')->ignore($pengguna)],
            'password' => ['nullable', 'string', 'min:8'],
            'peran' => ['required', Rule::in(['super_admin', 'admin', 'pegawai'])],
            'aktif' => ['required', 'boolean'],
        ]);
        if (! $data['password']) {
            unset($data['password']);
        }
        if ($pengguna->is($request->user())) {
            $data['peran'] = 'super_admin';
            $data['aktif'] = true;
        }
        abort_if(
            $pengguna->peran === 'super_admin'
            && $pengguna->aktif
            && ($data['peran'] !== 'super_admin' || ! $data['aktif'])
            && Pengguna::where(['peran' => 'super_admin', 'aktif' => true])->count() === 1,
            422,
            'Super Admin aktif terakhir tidak dapat dinonaktifkan atau diubah perannya.',
        );
        $pengguna->update($data);

        return back()->with('sukses', 'Akun berhasil diperbarui.');
    }

    public function hapusAkun(Request $request, Pengguna $pengguna): RedirectResponse
    {
        abort_if($pengguna->is($request->user()), 422, 'Akun yang sedang digunakan tidak dapat dihapus.');
        abort_if(
            DB::table('surat')->where('dibuat_oleh', $pengguna->id)->exists()
            || DB::table('verifikasi_ttd')->where('diverifikasi_oleh', $pengguna->id)->exists()
            || DB::table('cadangan_data')->where('dibuat_oleh', $pengguna->id)->exists(),
            422,
            'Akun yang memiliki riwayat aktivitas tidak dapat dihapus. Nonaktifkan akun sebagai gantinya.',
        );
        $pengguna->delete();

        return back()->with('sukses', 'Akun berhasil dihapus.');
    }

    public function verifikasi(): View
    {
        return view('aplikasi', [
            'dokumen' => Surat::with('verifikasi')->whereNotNull('file')->latest('tanggal_surat')->get(),
            'riwayat' => VerifikasiTtd::with(['surat', 'pemeriksa'])->latest()->paginate(10),
            'halaman' => 'verifikasi',
        ]);
    }

    public function prosesVerifikasi(Request $request, Surat $surat, PemeriksaTandaTanganPdf $pemeriksa): RedirectResponse
    {
        abort_unless($surat->file && Storage::exists($surat->file), 404, 'File surat tidak tersedia.');
        $isi = Storage::get($surat->file);
        $valid = $pemeriksa->valid($isi);
        VerifikasiTtd::updateOrCreate(['surat_id' => $surat->id], [
            'nama_file' => basename($surat->file),
            'file' => $surat->file,
            'valid' => $valid,
            'keterangan' => $valid ? 'Tanda tangan digital atau visual terdeteksi pada dokumen.' : 'Tanda tangan tidak ditemukan pada dokumen.',
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return back()->with('sukses', 'Pemeriksaan dokumen surat selesai.');
    }

    public function cadangan(): View
    {
        return view('aplikasi', ['riwayat' => CadanganData::with('pembuat')->latest()->get(), 'halaman' => 'cadangan']);
    }

    public function buatCadangan(Request $request): RedirectResponse
    {
        $nama = 'cadangan_'.now()->format('Ymd_His_u').'.zip';
        $lokasi = Storage::path("cadangan/{$nama}");
        if (! is_dir(dirname($lokasi))) {
            mkdir(dirname($lokasi), 0755, true);
        }
        $zip = new ZipArchive;
        abort_unless($zip->open($lokasi, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500, 'Cadangan tidak dapat dibuat.');
        $tabel = ['users', 'kategori_dokumen', 'surat', 'verifikasi_ttd', 'cadangan_data'];
        $snapshot = DB::transaction(function () use ($tabel) {
            return collect($tabel)->mapWithKeys(fn ($nama) => [
                $nama => DB::table($nama)->get()->map(fn ($baris) => (array) $baris)->all(),
            ])->all();
        });
        $zip->addFromString('database/data.json', json_encode([
            'format' => 'sipadok-mysql-v1',
            'dibuat_pada' => now()->toIso8601String(),
            'tabel' => $snapshot,
        ], JSON_THROW_ON_ERROR));
        foreach (['surat', 'verifikasi'] as $folder) {
            foreach (Storage::allFiles($folder) as $file) {
                $zip->addFile(Storage::path($file), "arsip/{$file}");
            }
        }
        $zip->close();
        CadanganData::create(['nama_file' => $nama, 'ukuran' => filesize($lokasi), 'dibuat_oleh' => $request->user()->id]);

        return back()->with('sukses', 'Cadangan database berhasil dibuat.');
    }

    public function unduhCadangan(CadanganData $cadangan): StreamedResponse
    {
        abort_unless(Storage::exists("cadangan/{$cadangan->nama_file}"), 404);

        return Storage::download("cadangan/{$cadangan->nama_file}");
    }

    public function pulihkanCadangan(Request $request, PemulihCadangan $pemulih): RedirectResponse
    {
        $request->validate(['cadangan' => ['required', 'file', 'mimes:zip', 'max:51200']]);
        $pemulih->pulihkan($request->file('cadangan'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', 'Database dan file arsip berhasil dipulihkan. Silakan masuk kembali.');
    }
}
