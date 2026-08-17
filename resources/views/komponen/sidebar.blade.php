@php
    $peran = auth()->user()->peran;
    $tautan = [
        ['dasbor', 'dasbor', 'Dashboard', 'i-rumah', []],
        ['surat', 'surat', 'Surat Masuk', 'i-masuk', ['jenis' => 'masuk']],
        ['surat', 'surat', 'Surat Keluar', 'i-keluar', ['jenis' => 'keluar']],
        ['arsip', 'arsip', $peran === 'pegawai' ? 'Arsip Saya' : 'Arsip', 'i-arsip', []],
        ['laporan', 'laporan', 'Laporan', 'i-laporan', []],
        ['master-data', 'master-data', 'Master Data', 'i-data', []],
        ['akun', 'akun', 'Akun', 'i-akun', []],
        ['cadangan', 'cadangan', 'Backup & Restore', 'i-cadangan', []],
        ['verifikasi', 'verifikasi', 'Verifikasi TTD', 'i-verifikasi', []],
    ];
@endphp
<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
    <div class="identitas">
        <span class="logo-sekolah">S</span>
        <div>
            <strong>Sistem Informasi<br>Pengarsipan Dokumen</strong>
            <small>SLB Negeri Pembina<br>Samarinda</small>
        </div>
    </div>
    <nav>
        @foreach($tautan as [$kunci, $rute, $label, $ikon, $parameter])
            @continue($peran === 'pegawai' && !in_array($kunci, ['dasbor', 'arsip', 'laporan']))
            @continue($peran === 'admin' && in_array($kunci, ['master-data', 'akun', 'cadangan', 'verifikasi']))
            @php $aktif = $halaman === $kunci && ($kunci !== 'surat' || request()->route('jenis') === ($parameter['jenis'] ?? null)); @endphp
            <a href="{{ route($rute, $parameter) }}" class="{{ $aktif ? 'aktif' : '' }}">
                <svg><use href="#{{ $ikon }}"/></svg>
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
    <form class="keluar-mobile" method="post" action="{{ route('keluar') }}">
        @csrf
        <button type="submit">Keluar dari aplikasi</button>
    </form>
</aside>
