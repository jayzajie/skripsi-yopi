<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ ucwords(str_replace('-', ' ', $halaman)) }} - SIPADOK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="halaman-aplikasi">
    @include('komponen.ikon')
    <div class="app-shell">
        @include('komponen.sidebar')
        <div class="lapisan-sidebar" data-tutup-sidebar></div>
        <section class="area-utama">
            <header class="header-utama">
                <button class="tombol-ikon menu-mobile" data-buka-sidebar aria-label="Buka navigasi">
                    <svg><use href="#i-menu"/></svg>
                </button>
                <h1>
                    {{ match($halaman) {
                        'dasbor' => 'Dashboard',
                        'surat' => 'Surat '.ucfirst($jenis),
                        'arsip' => auth()->user()->peran === 'pegawai' ? 'Arsip Saya' : 'Arsip',
                        'detail-arsip' => 'Detail Arsip',
                        'laporan' => 'Laporan Arsip',
                        'master-data' => 'Master Data',
                        'akun' => 'Akun Pengguna',
                        'cadangan' => 'Backup & Restore',
                        'verifikasi' => 'Verifikasi Tanda Tangan',
                        default => 'SIPADOK'
                    } }}
                </h1>
                <div class="profil-header">
                    <svg><use href="#i-lonceng"/></svg>
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div>
                        <strong>{{ ucwords(str_replace('_', ' ', auth()->user()->peran)) }}</strong>
                        <small>{{ auth()->user()->name }}</small>
                    </div>
                    <form method="post" action="{{ route('keluar') }}">
                        @csrf
                        <button class="keluar" type="submit">Keluar</button>
                    </form>
                </div>
            </header>
            <main class="konten">
                @if(session('sukses'))
                    <div class="notifikasi sukses">
                        {{ session('sukses') }}
                        <button data-tutup-notifikasi aria-label="Tutup">×</button>
                    </div>
                @endif
                @if($errors->any())
                    <div class="notifikasi gagal">
                        <strong>Periksa kembali data:</strong> {{ $errors->first() }}
                        <button data-tutup-notifikasi aria-label="Tutup">×</button>
                    </div>
                @endif
                @include('halaman.'.$halaman)
            </main>
            <footer class="footer-aplikasi">© {{ date('Y') }} SLB Negeri Pembina Samarinda</footer>
        </section>
    </div>
</body>
</html>
