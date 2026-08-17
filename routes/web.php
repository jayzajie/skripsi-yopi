<?php

use App\Http\Controllers\PengendaliAdministrasi;
use App\Http\Controllers\PengendaliAplikasi;
use App\Http\Controllers\PengendaliAutentikasi;
use Illuminate\Support\Facades\Route;

Route::get('/', [PengendaliAutentikasi::class, 'formulir'])->name('login');
Route::post('/masuk', [PengendaliAutentikasi::class, 'masuk'])->middleware('throttle:5,1')->name('masuk.proses');

Route::middleware(['auth', 'aktif'])->group(function () {
    Route::post('/keluar', [PengendaliAutentikasi::class, 'keluar'])->name('keluar');
    Route::get('/dasbor', [PengendaliAplikasi::class, 'dasbor'])->name('dasbor');
    Route::get('/arsip', [PengendaliAplikasi::class, 'arsip'])->name('arsip');
    Route::get('/arsip/{surat}', [PengendaliAplikasi::class, 'detailArsip'])->name('arsip.detail');
    Route::get('/arsip/{surat}/pratinjau', [PengendaliAplikasi::class, 'pratinjauSurat'])->name('arsip.pratinjau');
    Route::get('/arsip/{surat}/unduh', [PengendaliAplikasi::class, 'unduhSurat'])->name('arsip.unduh');
    Route::get('/laporan', [PengendaliAplikasi::class, 'laporan'])->name('laporan');
    Route::get('/laporan/ekspor', [PengendaliAplikasi::class, 'eksporLaporan'])->name('laporan.ekspor');

    Route::middleware('peran:super_admin,admin')->group(function () {
        Route::get('/surat/{jenis}', [PengendaliAplikasi::class, 'surat'])->name('surat');
        Route::delete('/surat/{surat}', [PengendaliAplikasi::class, 'hapusSurat'])->name('surat.hapus');
    });

    Route::middleware('peran:admin')->group(function () {
        Route::post('/surat/{jenis}', [PengendaliAplikasi::class, 'simpanSurat'])->name('surat.simpan');
        Route::put('/surat/{surat}', [PengendaliAplikasi::class, 'perbaruiSurat'])->name('surat.perbarui');
        Route::post('/surat/{surat}/terkirim', [PengendaliAplikasi::class, 'tandaiTerkirim'])->name('surat.terkirim');
    });

    Route::middleware('peran:super_admin')->group(function () {
        Route::post('/surat/{surat}/proses', [PengendaliAplikasi::class, 'prosesSurat'])->name('surat.proses');
        Route::get('/verifikasi-ttd', [PengendaliAdministrasi::class, 'verifikasi'])->name('verifikasi');
        Route::post('/verifikasi-ttd/{surat}', [PengendaliAdministrasi::class, 'prosesVerifikasi'])->name('verifikasi.proses');
        Route::get('/master-data', [PengendaliAdministrasi::class, 'masterData'])->name('master-data');
        Route::post('/master-data', [PengendaliAdministrasi::class, 'simpanKategori'])->name('kategori.simpan');
        Route::put('/master-data/{kategori}', [PengendaliAdministrasi::class, 'perbaruiKategori'])->name('kategori.perbarui');
        Route::delete('/master-data/{kategori}', [PengendaliAdministrasi::class, 'hapusKategori'])->name('kategori.hapus');
        Route::get('/akun', [PengendaliAdministrasi::class, 'akun'])->name('akun');
        Route::post('/akun', [PengendaliAdministrasi::class, 'simpanAkun'])->name('akun.simpan');
        Route::put('/akun/{pengguna}', [PengendaliAdministrasi::class, 'perbaruiAkun'])->name('akun.perbarui');
        Route::delete('/akun/{pengguna}', [PengendaliAdministrasi::class, 'hapusAkun'])->name('akun.hapus');
        Route::get('/cadangan', [PengendaliAdministrasi::class, 'cadangan'])->name('cadangan');
        Route::post('/cadangan', [PengendaliAdministrasi::class, 'buatCadangan'])->name('cadangan.buat');
        Route::post('/cadangan/pulihkan', [PengendaliAdministrasi::class, 'pulihkanCadangan'])->name('cadangan.pulihkan');
        Route::get('/cadangan/{cadangan}/unduh', [PengendaliAdministrasi::class, 'unduhCadangan'])->name('cadangan.unduh');
    });

    Route::post('/surat/{surat}/selesaikan', [PengendaliAplikasi::class, 'selesaikanSurat'])
        ->middleware('peran:pegawai')->name('surat.selesaikan');
});
