<section class="toolbar panel-tipis">
    <form method="get" class="pencarian">
        <div class="input-ikon">
            <svg><use href="#i-cari"/></svg>
            <input name="cari" value="{{ request('cari') }}" placeholder="Cari arsip...">
        </div>
        <select name="jenis" aria-label="Filter jenis arsip">
            <option value="">Semua jenis</option>
            <option value="masuk" @selected(request('jenis') === 'masuk')>Surat Masuk</option>
            <option value="keluar" @selected(request('jenis') === 'keluar')>Surat Keluar</option>
        </select>
        <button class="tombol tombol-sekunder">Filter</button>
    </form>
</section>
<section class="panel">
    <div class="bungkus-tabel">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Jenis</th>
                    <th>Kategori</th>
                    <th>No. Agenda</th>
                    <th>Perihal</th>
                    <th>Penanggung Jawab</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($surat as $item)
                    <tr>
                        <td>{{ $surat->firstItem()+$loop->index }}</td>
                        <td>Surat {{ ucfirst($item->jenis) }}</td>
                        <td>{{ $item->kategori?->nama ?? 'Belum dikategorikan' }}</td>
                        <td>{{ $item->nomor_agenda }}</td>
                        <td>{{ $item->perihal }}</td>
                        <td>{{ $item->penanggungJawab?->name ?? '—' }}</td>
                        <td><span class="lencana {{ strtolower($item->status) }}">{{ $item->status }}</span></td>
                        <td>
                            <div class="aksi-tabel">
                                <a class="tombol tombol-mini tombol-sekunder" href="{{ route('arsip.detail', $item) }}">Lihat</a>
                                @if(auth()->user()->peran === 'pegawai' && $item->jenis === 'masuk' && $item->status === 'Didisposisikan')
                                    <form method="post" action="{{ route('surat.selesaikan', $item) }}" data-konfirmasi="Tandai tugas surat ini selesai?">
                                        @csrf
                                        <button class="tombol tombol-mini tombol-primer">Selesaikan</button>
                                    </form>
                                @endif
                                @if(auth()->user()->peran === 'super_admin')
                                    <form method="post" action="{{ route('surat.hapus', $item) }}" data-konfirmasi="Hapus surat ini beserta riwayat prosesnya?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="tombol-ikon bahaya" aria-label="Hapus surat">
                                            <svg><use href="#i-hapus"/></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="kosong">Arsip tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="bawah-tabel">{{ $surat->links() }}</div>
</section>
@if(auth()->user()->peran === 'pegawai')
    <section class="panel catatan">
        <h2>Catatan Akses Pegawai</h2>
        <ul>
            <li>Pegawai hanya dapat melihat dan mengunduh arsip sesuai hak akses.</li>
            <li>Fitur tambah, edit, dan hapus tidak tersedia untuk role Pegawai.</li>
            <li>Gunakan tombol Lihat untuk membuka detail arsip.</li>
        </ul>
    </section>
@endif
