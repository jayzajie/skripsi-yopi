<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - SIPADOK</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="halaman-masuk">
    @include('komponen.ikon')
    <main class="login-shell">
        <section class="login-visual">
            <div class="identitas identitas-besar">
                <span class="logo-sekolah">S</span>
                <div>
                    <strong>Sistem Informasi<br>Pengarsipan Dokumen</strong>
                    <small>SLB Negeri Pembina Samarinda</small>
                </div>
            </div>
            <div class="sambutan">
                <h1>Selamat Datang!</h1>
                <p>Kelola dan arsipkan dokumen<br>dengan mudah dan terorganisir.</p>
            </div>
            <div class="ilustrasi-arsip" aria-hidden="true">
                <span class="tanaman"></span>
                <span class="kertas kertas-1"></span>
                <span class="kertas kertas-2"></span>
                <span class="map">
                    <svg><use href="#i-verifikasi"/></svg>
                </span>
            </div>
        </section>
        <section class="login-form-area">
            <form class="kartu-login" method="post" action="{{ route('masuk.proses') }}">
                @csrf
                <h2>Masuk ke Akun Anda</h2>
                @if(session('sukses'))
                    <div class="notifikasi sukses">{{ session('sukses') }}</div>
                @endif
                <label for="username">Username</label>
                <div class="input-ikon">
                    <svg><use href="#i-akun"/></svg>
                    <input id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan username" autocomplete="username" required autofocus>
                </div>
                <label for="password">Password</label>
                <div class="input-ikon">
                    <svg><use href="#i-verifikasi"/></svg>
                    <input id="password" type="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                </div>
                @error('username')
                    <p class="pesan-error">{{ $message }}</p>
                @enderror
                <div class="baris-login">
                    <label class="cek"><input type="checkbox" name="ingat" value="1"> Ingat saya</label>
                    <button class="tautan-redup tombol-tautan" type="button" data-buka-modal="bantuan-password">Lupa password?</button>
                </div>
                <button class="tombol tombol-primer tombol-penuh" type="submit">Masuk</button>
                @env('local')
                    <p class="akun-demo">Demo lokal: superadmin / admin / pegawai &nbsp;•&nbsp; password dari konfigurasi</p>
                @endenv
            </form>
            <footer>© {{ date('Y') }} SLB Negeri Pembina Samarinda</footer>
        </section>
    </main>
    <dialog class="modal modal-bantuan" id="bantuan-password">
        <div class="kepala-modal">
            <h2>Bantuan Password</h2>
            <button type="button" class="tombol-ikon" data-tutup-modal aria-label="Tutup">
                <svg><use href="#i-tutup"/></svg>
            </button>
        </div>
        <div class="isi-modal">
            <p>Hubungi administrator sekolah untuk mengatur ulang password akun Anda.</p>
        </div>
        <div class="kaki-modal">
            <button type="button" class="tombol tombol-primer" data-tutup-modal>Mengerti</button>
        </div>
    </dialog>
</body>
</html>
