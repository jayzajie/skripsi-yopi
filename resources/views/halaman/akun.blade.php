<section class="panel">
    <div class="judul-panel">
        <h2>Akun Pengguna</h2>
        <button class="tombol tombol-primer tombol-mini" data-buka-modal="form-akun" data-akun-baru>
            <svg><use href="#i-tambah"/></svg> Tambah Pengguna
        </button>
    </div>
    <div class="bungkus-tabel">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pengguna as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $item->name }}</td>
                        <td>{{ $item->username }}</td>
                        <td>{{ $item->email }}</td>
                        <td>{{ ucwords(str_replace('_',' ',$item->peran)) }}</td>
                        <td>
                            <span class="lencana {{ $item->aktif?'aktif':'nonaktif' }}">{{ $item->aktif?'Aktif':'Nonaktif' }}</span>
                        </td>
                        <td>
                            <div class="aksi-tabel">
                                <button
                                    class="tombol-ikon"
                                    aria-label="Edit {{ $item->name }}"
                                    data-buka-modal="form-akun"
                                    data-edit-akun
                                    data-action="{{ route('akun.perbarui',$item) }}"
                                    data-isian="{{ base64_encode($item->toJson()) }}"
                                >
                                    <svg><use href="#i-edit"/></svg>
                                </button>
                                @if(!$item->is(auth()->user()))
                                    <form method="post" action="{{ route('akun.hapus',$item) }}" data-konfirmasi="Hapus akun ini?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tombol-ikon bahaya" aria-label="Hapus {{ $item->name }}">
                                            <svg><use href="#i-hapus"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
<section class="grid-akses">
    <article class="panel">
        <h2>Hak Akses per Role</h2>
        <div class="bungkus-tabel">
            <table>
                <thead>
                    <tr>
                        <th>Proses</th>
                        <th>Super Admin</th>
                        <th>Admin</th>
                        <th>Pegawai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['Input dan edit surat','Lihat','Kelola','—'],
                        ['Persetujuan dan disposisi','Kelola','—','—'],
                        ['Penyelesaian tugas','Pantau','Pantau','Kelola'],
                        ['Arsip dan laporan','Semua','Semua','Milik sendiri'],
                        ['Master data, akun, backup','Kelola','—','—'],
                        ['Verifikasi TTD','Kelola','—','—']
                    ] as $akses)
                        <tr>
                            <td>{{ $akses[0] }}</td>
                            @foreach(array_slice($akses,1) as $nilai)
                                <td class="centang">{{ $nilai }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>
    <article class="panel ringkasan-role">
        <h2>Alur Tiga Role</h2>
        <p><b>Admin:</b> mencatat dan melengkapi surat.</p>
        <p><b>Super Admin:</b> menyetujui, memverifikasi, dan menentukan penanggung jawab.</p>
        <p><b>Pegawai:</b> membuka tugas yang diberikan dan menyelesaikannya.</p>
    </article>
</section>
<dialog class="modal" id="form-akun">
    <form method="post" action="{{ route('akun.simpan') }}" data-form-akun>
        @csrf
        <input type="hidden" name="_method" value="POST" data-metode>
        <div class="kepala-modal">
            <h2 data-judul-modal>Tambah Pengguna</h2>
            <button type="button" class="tombol-ikon" aria-label="Tutup" data-tutup-modal>
                <svg><use href="#i-tutup"/></svg>
            </button>
        </div>
        <div class="isi-modal grid-form">
            <label>Nama <b>*</b><input name="name" required></label>
            <label>Username <b>*</b><input name="username" required></label>
            <label>Email <b>*</b><input type="email" name="email" required></label>
            <label>Password <span data-password-wajib>*</span><input type="password" name="password" minlength="8"></label>
            <label>
                Role <b>*</b>
                <select name="peran">
                    <option value="admin">Admin</option>
                    <option value="super_admin">Super Admin</option>
                    <option value="pegawai">Pegawai</option>
                </select>
            </label>
            <label>
                Status
                <select name="aktif">
                    <option value="1">Aktif</option>
                    <option value="0">Nonaktif</option>
                </select>
            </label>
        </div>
        <div class="kaki-modal">
            <button type="button" class="tombol tombol-sekunder" data-tutup-modal>Batal</button>
            <button class="tombol tombol-primer">Simpan</button>
        </div>
    </form>
</dialog>
